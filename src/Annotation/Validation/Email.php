<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Email extends BaseValidation
{
    protected mixed $rule = 'email';

    /**
     * 验证字段必须是格式正确的电子邮件地址
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}
