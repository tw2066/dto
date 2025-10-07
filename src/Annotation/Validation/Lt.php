<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Lt extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'lt';

    /**
     * 验证字段必须小于给定 field 字段，这两个字段类型必须一致，适用于字符串、数字、数组和文件，和 size 规则类似
     */
    public function __construct(mixed $value, string $messages = '')
    {
        $this->rule .= ':' . $value;
        parent::__construct($messages);
    }
}
