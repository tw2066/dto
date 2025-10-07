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
     * @param mixed $value 当为 array 规则提供其他值时，输入数组中的每个键都必须存在于提供给规则的值列表中。
     */
    public function __construct(mixed $value = '', string $message = '')
    {
        $value && $this->rule .= ':' . $value;
        parent::__construct($message);
    }
}
