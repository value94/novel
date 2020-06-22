<?php
namespace addons\BaiduLinkAutomatic;
use app\common\addons\Addon;

class BaiduLinkAutomatic extends Addon{

    public $info = [
        'name'=>'BaiduLinkAutomatic',
        'title'=>'百度搜索自动推送',
        'description'=>'百度搜索自动推送',
        'status'=>1,
        'author'=>'kyxscms',
        'version'=>'1.0.0',
        'group'=>'home_js',
        'mold'=>'web,wap,wechat',
        'sort'=>0,
        'exclusive'=>0
    ];

    public function install(){
        return true;
    }

    public function uninstall(){
        return true;
    }

    public function run(){
        $html = <<<END
\r\n<script>
    (function(){
        var bp = document.createElement('script');
        var curProtocol = window.location.protocol.split(':')[0];
        if (curProtocol === 'https') {
            bp.src = 'https://zz.bdstatic.com/linksubmit/push.js';
        }
        else {
            bp.src = 'http://push.zhanzhang.baidu.com/push.js';
        }
        var s = document.getElementsByTagName("script")[0];
        s.parentNode.insertBefore(bp, s);
    })();
</script>
END;
        return $html;
    }
}