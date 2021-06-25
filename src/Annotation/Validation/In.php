<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;
use Hyperf\Validation\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class In extends BaseValidation
{
    /**
     * In constructor.
     */
    public function __construct(array $in, string $messages = '')
    {
        $this->messages = $messages;
        $this->rule = Rule::in($in);
    }
}
