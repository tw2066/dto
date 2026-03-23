<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MaxDigits extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'max_digits';

    /**
     * 验证字段必须小于或等于指定的数字位数.
     */
    public function __construct(int $max, string $message = '')
    {
        $this->rule .= ':' . $max;
        parent::__construct($message);
    }
}

