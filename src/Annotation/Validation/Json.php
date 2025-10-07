<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Json extends BaseValidation
{
    protected mixed $rule = 'json';

    /**
     * 验证字段必须是有效的 JSON 字符串.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}
