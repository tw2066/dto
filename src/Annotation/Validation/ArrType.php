<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ArrType extends BaseValidation
{
    /**
     * 验证数组中值的类型.
     */
    public function __construct(mixed $value, string $messages = '')
    {
        $this->rule = $value;
        parent::__construct($messages);
    }

    public function setFieldName(string $fieldName): void
    {
        $this->fieldName = $fieldName . '.*';
    }
}
