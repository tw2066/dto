<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DateFormat extends BaseValidation
{
    protected mixed $rule = 'date_format';

    /**
     * 验证字段必须匹配指定格式，可以使用 PHP 函数 date 或 date_format 验证该字段.
     */
    public function __construct(string $format = 'Y-m-d H:i:s', public string $messages = '')
    {
        $this->rule .= ':' . $format;
        parent::__construct($messages);
    }
}
