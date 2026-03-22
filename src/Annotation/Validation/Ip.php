<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Ip extends BaseValidation
{
    protected mixed $rule = 'ip';

    /**
     * 验证字段必须是 IP 地址.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}

