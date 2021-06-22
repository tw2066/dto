<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
class Required extends BaseValidation
{
    public $rule = 'required';
}
