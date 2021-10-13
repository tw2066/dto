<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Between extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'between';

    /**
     * Between constructor.
     */
    public function __construct(int $min, int $max, string $messages = '')
    {
        $this->rule = $this->rule . ':' . $min . ',' . $max;
        $this->messages = $messages;
    }
}
