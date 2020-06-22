<?php
// +----------------------------------------------------------------------
// | KyxsCMS [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018~2019 http://www.kyxscms.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: kyxscms
// +----------------------------------------------------------------------

namespace app\home\controller;

use app\common\controller\Base;
use app\common\model\Api;
use think\facade\Cookie;

class Novel extends Base
{
    public function index()
    {
        Cookie::set('__forward__', $this->request->url());
        // 查询小说数据
        $id = $this->request->param('id');
        $apiModel = new Api();
        $info = $apiModel->novel_detail($id);
        if (!$info) {
            $error = $apiModel->getError();
            $this->error(empty($error) ? '未找到该小说！' : $error, url('Home/Index/index'));
        }

        // 获取模板
        if (empty($info['template'])) {
            $tpl = $apiModel->get_tpl($info['cid'], 'template_detail');
            if (empty($tpl)) {
                $error = $apiModel->getError();
                $this->error(empty($error) ? '未知错误！' : $error);
            }
        } else {
            $tpl = $info['template'];
        }

        $apiModel->hits($id, 'novel');
        $is_bookshelf = model('user/bookshelf')->check($info['id']);

        $this->assign('pos', 1);
        $this->assign('type', 'novel');
        $this->assign($info);
        $this->assign('reader_url', $apiModel->novel_reader_url($info['id']));
        $this->assign('is_bookshelf', $is_bookshelf ? $is_bookshelf : 0);
        $this->assign('add_bookshelf', 'onclick=add_bookshelf()');

        return $this->fetch($this->home_tplpath . $tpl);
    }

    public function digg($id, $digg)
    {
        $apiModel = new Api();
        $return = $apiModel->digg($id, 'novel', $digg);
        if ($return) {
            return $this->success($digg . '+1');
        } else {
            $this->error('请不要重复操作！');
        }
    }
}
