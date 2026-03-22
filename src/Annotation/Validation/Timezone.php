<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Timezone extends BaseValidation
{
    protected mixed $rule = 'timezone';

    /**
     * 验证字段必须是有效的时区标识符.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}

