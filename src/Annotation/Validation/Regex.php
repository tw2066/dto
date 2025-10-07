<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Regex extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'regex';

    /**
     * 验证字段必须匹配给定正则表达式。 该规则底层使用的是 PHP 的 preg_match 函数。因此，指定的模式需要遵循 preg_match 函数所要求的格式并且包含有效的分隔符。\
     * 例如:/^.+@.+$/i.
     */
    public function __construct(string $value, string $messages = '')
    {
        parent::__construct($messages);
        $this->rule .= ':' . $value;
    }
}
