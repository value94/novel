<?php
namespace addons\QiniuBook\Qiniu;

use addons\QiniuBook\Qiniu\Zone;
use addons\QiniuBook\Qiniu\Config;
use addons\QiniuBook\Qiniu\Http\Client;
use addons\QiniuBook\Qiniu\Http\Error;

final class QiniuStorage
{
    private $accessKey;
    private $secretKey;

    public function __construct($accessKey, $secretKey)
    {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
    }

    public function sign($data)
    {
        $hmac = hash_hmac('sha1', $data, $this->secretKey, true);
        return $this->accessKey . ':' . $this->base64_urlSafeEncode($hmac);
    }

    public function signWithData($data)
    {
        $encodedData = $this->base64_urlSafeEncode($data);
        return $this->sign($encodedData) . ':' . $encodedData;
    }

    public function signRequest($urlString, $body, $contentType = null)
    {
        $url = parse_url($urlString);
        $data = '';
        if (array_key_exists('path', $url)) {
            $data = $url['path'];
        }
        if (array_key_exists('query', $url)) {
            $data .= '?' . $url['query'];
        }
        $data .= "\n";

        if ($body !== null && $contentType === 'application/x-www-form-urlencoded') {
            $data .= $body;
        }
        return $this->sign($data);
    }

    public function verifyCallback($contentType, $originAuthorization, $url, $body)
    {
        $authorization = 'QBox ' . $this->signRequest($url, $body, $contentType);
        return $originAuthorization === $authorization;
    }

    public function privateDownloadUrl($baseUrl, $expires = 3600)
    {
        $deadline = time() + $expires;

        $pos = strpos($baseUrl, '?');
        if ($pos !== false) {
            $baseUrl .= '&e=';
        } else {
            $baseUrl .= '?e=';
        }
        $baseUrl .= $deadline;

        $token = $this->sign($baseUrl);
        return "$baseUrl&token=$token";
    }

    public function uploadToken($bucket, $key = null, $expires = 3600, $policy = null, $strictPolicy = true)
    {
        $deadline = time() + $expires;
        $scope = $bucket;
        if ($key !== null) {
            $scope .= ':' . $key;
        }

        $args = self::copyPolicy($args, $policy, $strictPolicy);
        $args['scope'] = $scope;
        $args['deadline'] = $deadline;

        $b = json_encode($args);
        return $this->signWithData($b);
    }

    public function read($baseUrl){
        $signedUrl = $this->privateDownloadUrl($baseUrl);
        $response = Client::get($signedUrl);
        if (!$response->ok()) {
            return array(null, new Error($signedUrl, $response));
        }
        return $response->body;
    }

    /**
     * 上传二进制流到七牛, 内部使用
     *
     * @param $upToken    上传凭证
     * @param $key        上传文件名
     * @param $data       上传二进制流
     * @param $config     上传配置
     * @param $params     自定义变量，规格参考
     *                    http://developer.qiniu.com/docs/v6/api/overview/up/response/vars.html#xvar
     * @param $mime       上传数据的mimeType
     *
     * @return array    包含已上传文件的信息，类似：
     *                                              [
     *                                                  "hash" => "<Hash string>",
     *                                                  "key" => "<Key string>"
     *                                              ]
     */
    public function put($upToken,$key,$data,$params = null,$mime = 'application/octet-stream',$fname = null) 
    {

        $fields = array('token' => $upToken);
        if ($key === null) {
            $fname='nullkey';
        } else {
            $fields['key'] = $key;
        }

        //enable crc32 check by default
        $fields['crc32'] = $this->crc32_data($data);

        if ($params) {
            foreach ($params as $k => $v) {
                $fields[$k] = $v;
            }
        }

        list($accessKey, $bucket, $err) = $this->explodeUpToken($upToken);
        if ($err != null) {
            return array(null, $err);
        }
        $config = new Config();
        $upHost = $config->getUpHost($accessKey, $bucket);

        $response = Client::multipartPost($upHost, $fields, 'file', $fname, $data, $mime);
        if (!$response->ok()) {
            return array(null, new Error($upHost, $response));
        }
        return array($response->json(), null);
    }

    /**
     * 删除指定资源
     *
     * @param $bucket     待删除资源所在的空间
     * @param $key        待删除资源的文件名
     *
     * @return mixed      成功返回NULL，失败返回对象Qiniu\Http\Error
     * @link  http://developer.qiniu.com/docs/v6/api/reference/rs/delete.html
     */
    public function delete($bucket, $key)
    {
        if(is_array($key)){
            $path = '/batch';
            $body = 'op=' . implode('&op=', $key);
        }else{
            $path = '/delete/' . $this->entry($bucket, $key); 
            $body = null;
        }
        $config = new Config();
        $url = $config->getRsHost($this->accessKey, $bucket) . $path;
        $headers = $this->authorization($url, $body, 'application/x-www-form-urlencoded');
        $ret = Client::post($url, $body, $headers);
        if (!$ret->ok()) {
            return array(null, new Error($url, $ret));
        }
        $r = ($ret->body === null) ? array() : $ret->json();
        return array($r, null);
    }

    /**
     *上传策略，参数规格详见
     *http://developer.qiniu.com/docs/v6/api/reference/security/put-policy.html
     */
    private static $policyFields = array(
        'callbackUrl',
        'callbackBody',
        'callbackHost',
        'callbackBodyType',
        'callbackFetchKey',

        'returnUrl',
        'returnBody',

        'endUser',
        'saveKey',
        'insertOnly',

        'detectMime',
        'mimeLimit',
        'fsizeMin',
        'fsizeLimit',

        'persistentOps',
        'persistentNotifyUrl',
        'persistentPipeline',

        'deleteAfterDays',
        'fileType',
        'isPrefixalScope',
    );

    private static function copyPolicy(&$policy, $originPolicy, $strictPolicy)
    {
        if ($originPolicy === null) {
            return array();
        }
        foreach ($originPolicy as $key => $value) {
            if (!$strictPolicy || in_array((string)$key, self::$policyFields, true)) {
                $policy[$key] = $value;
            }
        }
        return $policy;
    }

    public function authorization($url, $body = null, $contentType = null)
    {
        $authorization = 'QBox ' . $this->signRequest($url, $body, $contentType);
        return array('Authorization' => $authorization);
    }

    public function authorizationV2($url, $method, $body = null, $contentType = null)
    {
        $urlItems = parse_url($url);
        $host = $urlItems['host'];

        if (isset($urlItems['port'])) {
            $port = $urlItems['port'];
        } else {
            $port = '';
        }

        $path = $urlItems['path'];
        if (isset($urlItems['query'])) {
            $query = $urlItems['query'];
        } else {
            $query = '';
        }

        //write request uri
        $toSignStr = $method . ' ' . $path;
        if (!empty($query)) {
            $toSignStr .= '?' . $query;
        }

        //write host and port
        $toSignStr .= "\nHost: " . $host;
        if (!empty($port)) {
            $toSignStr .= ":" . $port;
        }

        //write content type
        if (!empty($contentType)) {
            $toSignStr .= "\nContent-Type: " . $contentType;
        }

        $toSignStr .= "\n\n";

        //write body
        if (!empty($body)) {
            $toSignStr .= $body;
        }

        $sign = $this->sign($toSignStr);
        $auth = 'Qiniu ' . $sign;
        return array('Authorization' => $auth);
    }

    /**
     * 对提供的数据进行urlsafe的base64编码。
     *
     * @param string $data 待编码的数据，一般为字符串
     *
     * @return string 编码后的字符串
     * @link http://developer.qiniu.com/docs/v6/api/overview/appendix.html#urlsafe-base64
     */
    private function base64_urlSafeEncode($data)
    {
        $find = array('+', '/');
        $replace = array('-', '_');
        return str_replace($find, $replace, base64_encode($data));
    }

    /**
     * 对提供的urlsafe的base64编码的数据进行解码
     *
     * @param string $str 待解码的数据，一般为字符串
     *
     * @return string 解码后的字符串
     */
    private function base64_urlSafeDecode($str)
    {
        $find = array('-', '_');
        $replace = array('+', '/');
        return base64_decode(str_replace($find, $replace, $str));
    }

    /**
     * 计算输入流的crc32检验码
     *
     * @param $data 待计算校验码的字符串
     *
     * @return string 输入字符串的crc32校验码
     */
    private function crc32_data($data)
    {
        $hash = hash('crc32b', $data);
        $array = unpack('N', pack('H*', $hash));
        return sprintf('%u', $array[1]);
    }

    /**
     *  从uptoken解析accessKey和bucket
     *
     * @param $upToken
     * @return array(ak,bucket,err=null)
     */
    private function explodeUpToken($upToken)
    {
        $items = explode(':', $upToken);
        if (count($items) != 3) {
            return array(null, null, "invalid uptoken");
        }
        $accessKey = $items[0];
        $putPolicy = json_decode($this->base64_urlSafeDecode($items[2]));
        $scope = $putPolicy->scope;
        $scopeItems = explode(':', $scope);
        $bucket = $scopeItems[0];
        return array($accessKey, $bucket, null);
    }

    /**
     * 计算七牛API中的数据格式
     *
     * @param $bucket 待操作的空间名
     * @param $key 待操作的文件名
     *
     * @return string  符合七牛API规格的数据格式
     * @link http://developer.qiniu.com/docs/v6/api/reference/data-formats.html
     */
    private function entry($bucket, $key)
    {
        $en = $bucket;
        if (!empty($key)) {
            $en = $bucket . ':' . $key;
        }
        return $this->base64_urlSafeEncode($en);
    }
}
