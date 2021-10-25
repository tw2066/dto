<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Hyperf\Di\Annotation\AbstractAnnotation;

abstract class BaseValidation extends AbstractAnnotation
{
    public string $messages = '';

    protected mixed $rule;

    /**
     * BaseValidation constructor.
     */
    public function __construct(string $messages = '')
    {
        $this->messages = $messages;
    }

    public function getRule(): mixed
    {
        return $this->rule;
    }
}
