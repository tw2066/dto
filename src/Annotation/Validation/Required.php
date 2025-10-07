<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Required extends BaseValidation
{
    protected mixed $rule = 'required';

    /**
     * 验证字段值不能为空，以下情况字段值都为空： 值为null 值是空字符串 值是空数组或者空的 Countable 对象 值是上传文件但路径为空.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}
