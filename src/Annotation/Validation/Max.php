<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Max extends BaseValidation
{
    /**
     * @var string
     */
    protected $rule = 'max';

    /**
     * Max constructor.
     */
    public function __construct(int $value, string $messages = '')
    {
        $this->messages = $messages;
        $this->rule = $this->rule . ':' . $value;
    }
}
