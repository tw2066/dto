<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Contains extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'contains';

    /**
     * 验证字段必须包含给定的值.
     * @param array $values 允许值列表
     */
    public function __construct(array $values, string $message = '')
    {
        $this->rule .= ':' . implode(',', $values);
        parent::__construct($message);
    }
}

