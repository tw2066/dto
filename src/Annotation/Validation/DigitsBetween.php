<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DigitsBetween extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'digits_between';

    /**
     * 验证字段必须在指定长度范围内的数字.
     */
    public function __construct(int $min, int $max, string $message = '')
    {
        $this->rule .= ':' . $min . ',' . $max;
        parent::__construct($message);
    }
}

