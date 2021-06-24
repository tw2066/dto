<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Validation extends BaseValidation
{
    /**
     * Validation constructor.
     */
    public function __construct(string $rule, string $messages = '')
    {
        $this->rule = $rule;
        $this->messages = $messages;
    }
}
