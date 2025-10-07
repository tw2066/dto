<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Boolean extends BaseValidation
{
    protected mixed $rule = 'boolean';

    /**
     * 验证字段必须能够转换为布尔值。接受的输入为 true、false、1、0、"1" 和 "0".
     * @param bool $strict 等于true时, 仅在字段值为 true 或 false 时才认为该字段有效
     */
    public function __construct(bool $strict = false, string $message = '')
    {
        $strict && $this->rule .= ':strict';
        parent::__construct($message);
    }
}
