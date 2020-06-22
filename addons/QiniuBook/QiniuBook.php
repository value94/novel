<?php
namespace addons\QiniuBook;
use think\facade\Cache;
use app\common\addons\Addon;
use addons\QiniuBook\Qiniu\QiniuStorage;

class QiniuBook extends Addon{

    public $info = [
        'name'=>'QiniuBook',
        'title'=>'七牛云存储小说',
        'description'=>'七牛云存储-储存小说内容',
        'status'=>1,
        'author'=>'kyxscms',
        'version'=>'1.0.0',
        'group'=>'storage',
        'mold'=>'web,wap,wechat',
        'sort'=>0,
        'exclusive'=>1
    ];

    protected $addon_config;

    protected $qiniu;

    protected function initialize(){
        parent::initialize();
        $this->addon_config=$this->getConfig();
        $this->qiniu = new QiniuStorage($this->addon_config['access_key'],$this->addon_config['secret_key']);
    }

    public function install(){
        Cache::rm('addons_storage');
        return true;
    }

    public function uninstall(){
        Cache::rm('addons_storage');
        return true;
    }

    public function read($object){
        $object = 'http://'.$this->addon_config['domain'].'/'.$object;
        $result = $this->qiniu->read(trim($object));
        return $result;
    }

    public function put($object,$content){
        $token = $this->qiniu->uploadToken($this->addon_config['bucket']);
        $result = $this->qiniu->put($token, $object, $content);
        return $result;
    }

    public function unlink($object){
        if(is_array($object)){
            if(count($object)>1000){
                $chunk_result = array_chunk($object, 1000);
                foreach ($chunk_result as $value) {
                    $this->qiniu->delete($this->addon_config['bucket'],$value);
                }
            }else{
                $this->qiniu->delete($this->addon_config['bucket'],$object);
            }
        }else{
            $this->qiniu->delete($this->addon_config['bucket'],$object);
        }
        return true;
    }
}