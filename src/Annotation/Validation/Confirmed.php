<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Confirmed extends BaseValidation
{
    protected mixed $rule = 'confirmed';

    /**
     * 验证字段必须与 foo_confirmation 字段的值相同.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}

