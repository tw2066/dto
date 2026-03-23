<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Same extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'same';

    /**
     * 验证字段必须与给定字段值相同.
     */
    public function __construct(string $field, string $message = '')
    {
        $this->rule .= ':' . $field;
        parent::__construct($message);
    }
}

