<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Max extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'max';

    /**
     * 验证字段必须小于等于最大值，和字符串、数值、数组、文件字段的 size 规则使用方式一样.
     */
    public function __construct(mixed $value, string $message = '')
    {
        parent::__construct($message);
        $this->rule .= ':' . $value;
    }
}
