<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Accepted extends BaseValidation
{
    protected mixed $rule = 'accepted';

    /**
     * 验证字段的值必须是 yes、on、1 或 true，这在「同意服务协议」时很有用.
     */
    public function __construct(string $messages = '')
    {
        parent::__construct($messages);
    }
}
