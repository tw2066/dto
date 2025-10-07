<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Present extends BaseValidation
{
    protected mixed $rule = 'present';

    /**
     * 验证字段必须出现在输入数据中但可以为空.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}
