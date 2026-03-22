<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Decimal extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'decimal';

    /**
     * 验证字段必须具有指定的小数位数.
     */
    public function __construct(int $min, ?int $max = null, string $message = '')
    {
        $this->rule .= ':' . $min;
        if (! is_null($max)) {
            $this->rule .= ',' . $max;
        }
        parent::__construct($message);
    }
}

