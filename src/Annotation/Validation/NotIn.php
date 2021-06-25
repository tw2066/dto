<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;
use Hyperf\Validation\Rule;

/**
 * 验证字段值不能在给定列表中，和 in 规则类似
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class NotIn extends BaseValidation
{
    /**
     * In constructor.
     */
    public function __construct(array $notIn, string $messages = '')
    {
        $this->messages = $messages;
        $this->rule = Rule::notIn($notIn);
    }
}
