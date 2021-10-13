<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

/**
 * 验证字段的值必须是 yes、on、1 或 true，这在「同意服务协议」时很有用.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Accepted extends BaseValidation
{
    protected mixed $rule = 'accepted';
}
