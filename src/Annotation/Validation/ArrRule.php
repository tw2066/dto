<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ArrRule extends BaseValidation
{
    /**
     * 验证数组中值的类型.
     * @param mixed $rule 验证规则 eg: 'integer' 'string'
     */
    public function __construct(mixed $rule, string $message = '')
    {
        $this->rule = $rule;
        parent::__construct($message);
    }

    public function setFieldName(string $fieldName): void
    {
        $this->fieldName = $fieldName . '.*';
    }
}
