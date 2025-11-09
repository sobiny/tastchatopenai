<?php
return [
    'app_debug'              => true,
    'app_trace'              => false,
    'default_module'         => 'index',
    'default_controller'     => 'Index',
    'default_action'         => 'index',
    'default_return_type'    => 'json',
    'default_ajax_return'    => 'json',
    'timezone'               => 'Asia/Shanghai',
    'extra_config_list'      => ['midjourney'],
    'log'                    => [
        'type' => 'File',
        'path' => LOG_PATH,
        'level' => ['error', 'warning', 'notice', 'info'],
    ],
];
