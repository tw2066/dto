<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

/**
 * 验证字段必须是字符串，如果允许字段为空，需要分配 nullable 规则到该字段。
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Str extends BaseValidation
{
    protected $rule = 'string';
}
