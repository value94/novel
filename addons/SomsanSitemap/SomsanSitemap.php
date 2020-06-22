<?php
namespace addons\SomsanSitemap;

use app\common\addons\Addon;
use think\Db;
use think\facade\Cache;
use think\facade\Env;

class SomsanSitemap extends Addon{

    public $info = [
        'name'=>'SomsanSitemap',
        'title'=>'sitemap生成',
        'description'=>'sitemap生成',
        'status'=>1,
        'author'=>'Somsan',
        'version'=>'1.0.0',
        'group'=>'data_operation',
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

    public function run($params){
        if (isset($params['type']) && $params['type'] == 'add' && isset($params['data'])){
            $config = $this->getConfig();

            if (!Cache::has('addons_somsan_sitemap_update_num')){
                $list = Db::name('novel')->order('update_time', 'desc')->limit($config['set_limit'] - 1)->select();
                $sitemap = new Sitemap(request()->domain());
                $sitemap->setXmlFile(Env::get('root_path').'sitemap');
                $sitemap->setIsChemaMore(false);
                $sitemap->addItem('/', '1.0', 'daily', 'Today');
                foreach ($list as $item) {
                    $sitemap->addItem('/novel/'.$item['id'].'.html', '0.8', 'daily', date('Y-m-d H:i:s', $item['update_time']));
                }
                $sitemap->endSitemap();
                Cache::set('addons_somsan_sitemap_update_num', 0);
            }else{
                $update_num = Cache::get('addons_somsan_sitemap_update_num');
                $update_num = $update_num + 1;
                if ($update_num >= $config['update_num']){
                    Cache::rm('addons_somsan_sitemap_update_num');
                }else{
                    Cache::set('addons_somsan_sitemap_update_num', $update_num);
                }
            }
        }
    }
}
