<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Missing extends BaseValidation
{
    protected mixed $rule = 'missing';

    /**
     * 验证字段在输入数据中必须缺失.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}

