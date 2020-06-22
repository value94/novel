<?php
namespace addons\StatisticalCode;
use app\common\addons\Addon;

class StatisticalCode extends Addon{

    public $info = [
        'name'=>'StatisticalCode',
        'title'=>'统计代码',
        'description'=>'统计代码',
        'status'=>1,
        'author'=>'kyxscms',
        'version'=>'1.0.0',
        'group'=>'home_js',
        'mold'=>'web,wap,wechat',
        'sort'=>0,
        'exclusive'=>0
    ];

    protected $addon_config;

    protected function initialize(){
        parent::initialize();
        $this->addon_config=$this->getConfig();
    }

    public function install(){
        return true;
    }

    public function uninstall(){
        return true;
    }

    public function run(){
        return $this->addon_config['code'];
    }
}