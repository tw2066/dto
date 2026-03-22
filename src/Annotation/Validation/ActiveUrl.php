<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ActiveUrl extends BaseValidation
{
    protected mixed $rule = 'active_url';

    /**
     * 验证字段必须是一个有效的 A 或 AAAA 记录的 URL.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}

