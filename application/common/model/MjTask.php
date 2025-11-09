<?php
namespace app\common\model;

use think\Model;

class MjTask extends Model
{
    protected $table = 'mj_tasks';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $type = [
        'meta' => 'array',
    ];
}
