<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
class Integer extends BaseValidation
{
    public $rule = 'integer';
}
