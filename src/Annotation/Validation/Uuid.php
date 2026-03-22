<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Uuid extends BaseValidation
{
    protected mixed $rule = 'uuid';

    /**
     * 验证字段必须是有效的 UUID.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}

