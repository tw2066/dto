<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Regex extends BaseValidation
{
    /**
     * @var string
     */
    protected $rule = 'regex';

    /**
     * Regex constructor.
     */
    public function __construct(string $value, string $messages = '')
    {
        $this->messages = $messages;
        $this->rule = $this->rule . ':' . $value;
    }
}
