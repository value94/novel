<?php
namespace addons\SoGoAutomatic;
use think\facade\Request;
use app\common\addons\Addon;

class SoGoAutomatic extends Addon{

    public $info = [
        'name'=>'SoGoAutomatic',
        'title'=>'360搜索智能摘要',
        'description'=>'360搜索智能摘要',
        'status'=>1,
        'author'=>'kyxscms',
        'version'=>'1.0.0',
        'group'=>'home_replace_html',
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

    public function run($param){
        $html='';
        if(in_array(strtolower(Request::controller()."/".Request::action()),['news/index','novel/index'])){
            $update_time=time_format($param['view']->__get('update_time'));
            $content=strip_tags($param['view']->__get('content'));
            if(strtolower(Request::controller())=='news'){
                $html = <<<END
\r\n        <meta property="og:type" content="news"/>
        <meta property="og:title" content="{$param['view']->__get('title')}"/>
        <meta property="og:description" content="{$content}"/>
        <meta property="og:image" content="{$param['view']->__get('pic')}"/>
        <meta property="og:url" content="{$param['view']->__get('url')}"/>
        <meta property="og:release_date" content="{$update_time}"/>
END;
            }else{
                $html = <<<END
\r\n        <meta property="og:type" content="novel"/>
        <meta property="og:title" content="{$param['view']->__get('title')}"/>
        <meta property="og:description" content="{$content}"/>
        <meta property="og:image" content="{$param['view']->__get('pic')}"/>
        <meta property="og:novel:category" content="{$param['view']->__get('ctitle')}"/>
        <meta property="og:novel:author" content="{$param['view']->__get('author')}"/>
        <meta property="og:novel:book_name" content="{$param['view']->__get('title')}"/>
        <meta property="og:novel:read_url" content="{$param['view']->__get('url')}"/>
        <meta property="og:novel:status" content="{$param['view']->__get('serialize_text')}"/>
        <meta property="og:novel:author_link" content="{$param['view']->__get('author_url')}"/>
        <meta property="og:novel:update_time" content="{$update_time}"/>
        <meta property="og:novel:latest_chapter_name" content="{$param['view']->__get('chapter_title')}"/>
        <meta property="og:novel:latest_chapter_url" content="{$param['view']->__get('chapter_url')}"/>
END;
            }
            return str_replace('</title>','</title>'.$html,$param['html']);
        }else{
            return $param['html'];
        }
    }
}