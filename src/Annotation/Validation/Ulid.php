<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Ulid extends BaseValidation
{
    protected mixed $rule = 'ulid';

    /**
     * 验证字段必须是有效的 ULID.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}

