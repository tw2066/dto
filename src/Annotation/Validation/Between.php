<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Between extends BaseValidation
{
    /**
     * @var int
     */
    public $min;

    /**
     * @var int
     */
    public $max;

    /**
     * @var string
     */
    protected $rule = 'between';

    /**
     * Between constructor.
     */
    public function __construct(int $min, int $max, string $messages = '')
    {
        $this->min = $min;
        $this->max = $max;
        $this->rule = $this->rule . ':' . $this->min . ',' . $this->max;
        $this->messages = $messages;
    }
}
