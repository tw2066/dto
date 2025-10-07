<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class NotRegex extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'not_regex';

    /**
     * 验证字段不能匹配给定正则表达式
     * 注：使用 regex/not_regex 模式时，规则必须放在数组中，而不能使用管道分隔符，尤其是正则表达式中包含管道符号时。
     */
    public function __construct(string $value, string $message = '')
    {
        parent::__construct($message);
        $this->rule .= ':' . $value;
    }
}
