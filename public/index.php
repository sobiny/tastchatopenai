<?php
// [ 应用入口文件 ]

// 定义应用目录
define('APP_PATH', __DIR__ . '/../application/');
define('ROOT_PATH', dirname(__DIR__) . '/');
define('EXTEND_PATH', ROOT_PATH . 'extend/');

// 加载框架引导文件
require __DIR__ . '/../thinkphp/start.php';
