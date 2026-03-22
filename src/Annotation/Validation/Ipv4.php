<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Ipv4 extends BaseValidation
{
    protected mixed $rule = 'ipv4';

    /**
     * 验证字段必须是 IPv4 地址.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}

