<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Numeric extends BaseValidation
{
    protected mixed $rule = 'numeric';

    /**
     * 验证字段必须是数值
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}
