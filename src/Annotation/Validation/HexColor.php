<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class HexColor extends BaseValidation
{
    protected mixed $rule = 'hex_color';

    /**
     * 验证字段必须是有效的十六进制颜色值.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}

