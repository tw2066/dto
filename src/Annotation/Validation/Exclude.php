<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Exclude extends BaseValidation
{
    protected mixed $rule = 'exclude';

    /**
     * 指示验证器忽略该字段.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}

