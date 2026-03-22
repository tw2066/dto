<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ListRule extends BaseValidation
{
    protected mixed $rule = 'list';

    /**
     * 验证字段必须是一个列表数组（键必须是连续的整数，从 0 开始）.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}

