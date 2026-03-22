<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MultipleOf extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'multiple_of';

    /**
     * 验证字段必须是给定值的倍数.
     */
    public function __construct(int|float $value, string $message = '')
    {
        $this->rule .= ':' . $value;
        parent::__construct($message);
    }
}

