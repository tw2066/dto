<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Ascii extends BaseValidation
{
    protected mixed $rule = 'ascii';

    /**
     * 验证字段必须完全由 7 位 ASCII 字符组成.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}

