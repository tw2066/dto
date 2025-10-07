<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Date extends BaseValidation
{
    protected mixed $rule = 'date';

    /**
     * 验证字段必须是一个基于 PHP strtotime 函数的有效日期
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}
