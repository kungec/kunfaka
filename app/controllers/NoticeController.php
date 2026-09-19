<?php
/**
 * 前台: 公告列表/公告详情/自定义单页
 */
class NoticeController
{
    public function actionIndex()
    {
        $list = DB::fetchAll('SELECT * FROM notices WHERE status = 1 ORDER BY sort DESC, id DESC LIMIT 50');
        View::theme('notice_list', ['list' => $list, 'pageTitle' => '公告列表']);
    }

    public function actionDetail()
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $notice = $id > 0 ? DB::fetch('SELECT * FROM notices WHERE id = ? AND status = 1', [$id]) : null;
        if (!$notice) {
            View::theme('error', ['msg' => '公告不存在或已下架', 'pageTitle' => '公告不存在']);
            return;
        }
        View::theme('notice_detail', ['notice' => $notice, 'pageTitle' => $notice['title']]);
    }

    public function actionPage()
    {
        if (setting('singlepage_open') !== '1') {
            View::theme('error', ['msg' => '页面不存在', 'pageTitle' => '页面不存在']);
            return;
        }
        View::theme('notice_page', [
            'pageTitle' => setting('singlepage_title', '关于我们'),
            'spTitle' => setting('singlepage_title', '关于我们'),
            'spContent' => (string)setting('singlepage_content'),
        ]);
    }
}
