<?php
namespace app\common\validate;

use think\Validate;

class MjTaskValidate extends Validate
{
    protected $rule = [
        'prompt' => 'require|max:1200',
        'action' => 'require|in:IMAGINE,UPSCALE,VARIATION,DESCRIBE',
    ];

    protected $message = [
        'prompt.require' => '请输入提示词',
        'prompt.max' => '提示词不能超过1200个字符',
        'action.require' => '请选择操作类型',
        'action.in' => '未知的操作类型',
    ];
}
