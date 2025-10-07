<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Between extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'between';

    /**
     * 验证字段大小在给定的最小值和最大值之间，字符串、数字、数组和文件都可以像使用 size 规则一样使用该规则.
     */
    public function __construct(int $min, int $max, string $messages = '')
    {
        $this->rule .= ':' . $min . ',' . $max;
        parent::__construct($messages);
    }
}
