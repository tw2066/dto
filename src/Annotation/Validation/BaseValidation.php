<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Hyperf\Di\Annotation\AbstractAnnotation;

abstract class BaseValidation extends AbstractAnnotation
{
    /**
     * @var string
     */
    public $rule;

    /**
     * @var string
     */
    public $messages = '';
}
