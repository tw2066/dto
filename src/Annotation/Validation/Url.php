<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Url extends BaseValidation
{
    protected mixed $rule = 'url';

    /**
     * 验证字段必须是有效的 URL.
     */
    public function __construct(string $messages = '')
    {
        parent::__construct($messages);
    }
}
