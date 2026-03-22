<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Filled extends BaseValidation
{
    protected mixed $rule = 'filled';

    /**
     * 验证字段如果存在，则必须不为空.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}

