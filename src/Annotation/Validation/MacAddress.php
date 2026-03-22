<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MacAddress extends BaseValidation
{
    protected mixed $rule = 'mac_address';

    /**
     * 验证字段必须是 MAC 地址.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}

