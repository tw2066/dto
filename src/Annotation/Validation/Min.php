<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Min extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'min';

    /**
     * Max constructor.
     */
    public function __construct(int $value, string $messages = '')
    {
        $this->messages = $messages;
        $this->rule = $this->rule . ':' . $value;
    }
}
