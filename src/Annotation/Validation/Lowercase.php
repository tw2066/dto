<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Lowercase extends BaseValidation
{
    protected mixed $rule = 'lowercase';

    /**
     * 验证字段必须是小写字符串.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}

