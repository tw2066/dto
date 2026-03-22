<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MinDigits extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'min_digits';

    /**
     * 验证字段必须大于或等于指定的数字位数.
     */
    public function __construct(int $min, string $message = '')
    {
        $this->rule .= ':' . $min;
        parent::__construct($message);
    }
}

