<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Ipv6 extends BaseValidation
{
    protected mixed $rule = 'ipv6';

    /**
     * 验证字段必须是 IPv6 地址.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}

