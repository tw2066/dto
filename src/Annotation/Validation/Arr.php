<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Arr extends BaseValidation
{
    protected mixed $rule = 'array';

    /**
     * 验证字段必须是 PHP 数组.
     */
    public function __construct(string $messages = '')
    {
        parent::__construct($messages);
    }
}
