<?php
namespace app\index\controller;

use app\common\model\MjTask;
use think\Controller;
use think\View;

class Index extends Controller
{
    public function index()
    {
        $tasks = MjTask::order('created_at', 'desc')->limit(20)->select();
        $callbackUrl = config('midjourney.callback_url');

        $view = new View();
        $view->assign('tasks', $tasks);
        $view->assign('callbackUrl', $callbackUrl);
        $view->assign('callbackConfigured', !empty($callbackUrl));
        return $view->fetch();
    }
}
