<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Digits extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'digits';

    /**
     * 验证字段必须是指定长度的数字.
     */
    public function __construct(int $length, string $message = '')
    {
        $this->rule .= ':' . $length;
        parent::__construct($message);
    }
}

