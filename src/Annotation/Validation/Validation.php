<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Validation extends BaseValidation
{
    /**
     * Validation constructor.
     */
    public function __construct(mixed $rule, string $messages = '', string $customKey = '')
    {
        $this->rule = $rule;
        $this->messages = $messages;
        $this->customKey = $customKey;
    }
}
