<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Hyperf\Di\Annotation\AbstractMultipleAnnotation;

abstract class BaseValidation extends AbstractMultipleAnnotation
{
    public string $messages = '';

    protected mixed $rule;

    protected string $customKey = '';

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

    /**
     * 用户支持 customKey.* 的情况.
     */
    public function getCustomKey(): string
    {
        return $this->customKey;
    }
}
