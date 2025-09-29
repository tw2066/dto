<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Min extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'min';

    /**
     * 验证字段必须大于等于最小值，对字符串、数值、数组、文件字段而言，和 size 规则使用方式一致.
     */
    public function __construct(int $value, string $messages = '')
    {
        parent::__construct($messages);
        $this->rule = $this->rule . ':' . $value;
    }
}
