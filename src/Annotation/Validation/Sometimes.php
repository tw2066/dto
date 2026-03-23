<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Sometimes extends BaseValidation
{
    protected mixed $rule = 'sometimes';

    /**
     * 验证字段仅在该字段存在时才会进行其它规则校验.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}

