<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Distinct extends ArrRule
{
    /**
     * 处理数组时，验证字段不能包含重复值.
     */
    public function __construct(bool $ignoreCase = false, string $message = '')
    {
        $rule = 'distinct';
        $ignoreCase && $rule .= ':ignore_case';
        parent::__construct($rule, $message);
    }
}
