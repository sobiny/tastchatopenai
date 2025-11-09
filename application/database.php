<?php

use think\Env;

return [
    'type'        => 'sqlite',
    'database'    => Env::get('database.database', ROOT_PATH . 'database/mjportal.sqlite'),
    'prefix'      => '',
    'auto_timestamp' => true,
];
