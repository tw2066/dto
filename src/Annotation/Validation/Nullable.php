<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Nullable extends BaseValidation
{
    protected mixed $rule = 'nullable';

    /**
     * 验证字段可以是 null，这在验证一些可以为 null 的原始数据如整型或字符串时很有用。
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}
