<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Alpha extends BaseValidation
{
    protected mixed $rule = 'alpha';

    /**
     * 验证字段必须是字母(包含中文)。 为了将此验证规则限制在 ASCII 范围内的字符（a-z 和 A-Z），你可以为验证规则提供 ascii 选项.
     */
    public function __construct(string $characterEncoding = 'ascii', string $message = '')
    {
        $characterEncoding && $this->rule .= ':' . $characterEncoding;
        parent::__construct($message);
    }
}
