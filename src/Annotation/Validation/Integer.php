<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Integer extends BaseValidation
{
    protected mixed $rule = 'integer';

    /**
     * 验证字段必须是整型（String 和 Integer 类型都可以通过验证）.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}
