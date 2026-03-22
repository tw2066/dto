<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class InArray extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'in_array';

    /**
     * 验证字段值必须存在于另一个字段的值数组中.
     */
    public function __construct(string $anotherField, string $message = '')
    {
        $this->rule .= ':' . $anotherField;
        parent::__construct($message);
    }
}

