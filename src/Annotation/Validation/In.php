<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;
use Hyperf\Validation\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class In extends BaseValidation
{
    /**
     * 验证字段值必须在给定的列表中.
     */
    public function __construct(private array $value, public string $message = '')
    {
        $this->rule = Rule::in($this->value);
        parent::__construct($message);
    }

    public function getValue(): array
    {
        return $this->value;
    }
}
