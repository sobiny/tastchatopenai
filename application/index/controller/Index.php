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
        $view = new View();
        $view->assign('tasks', $tasks);
        $view->assign('callbackUrl', env('midjourney.callback_url', request()->domain() . '/api/task/callback'));
        return $view->fetch();
    }
}
