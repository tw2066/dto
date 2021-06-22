<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
class Email extends BaseValidation
{
    public $rule = 'email';
}
