<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DoesntStartWith extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'doesnt_start_with';

    /**
     * 验证字段不能以某个给定值开头.
     */
    public function __construct(string $value, string $message = '')
    {
        parent::__construct($message);
        $this->rule .= ':' . $value;
    }
}

