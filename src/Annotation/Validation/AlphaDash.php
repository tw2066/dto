<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class AlphaDash extends BaseValidation
{
    protected mixed $rule = 'alpha_dash';

    /**
     * 验证字段可以包含字母(包含中文)和数字，以及破折号和下划线。为了将此验证规则限制在 ASCII 范围内的字符（a-z 和 A-Z）.
     */
    public function __construct(string $characterEncoding = 'ascii', string $messages = '')
    {
        $characterEncoding && $this->rule .= ':' . $characterEncoding;
        parent::__construct($messages);
    }
}
