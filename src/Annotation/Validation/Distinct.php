<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Distinct extends ArrType
{
    /**
     * 处理数组时，验证字段不能包含重复值.
     */
    public function __construct(string $messages = '')
    {
        parent::__construct('distinct', $messages);
    }
}
