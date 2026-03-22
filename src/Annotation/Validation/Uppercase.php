<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Uppercase extends BaseValidation
{
    protected mixed $rule = 'uppercase';

    /**
     * 验证字段必须是大写字符串.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}

