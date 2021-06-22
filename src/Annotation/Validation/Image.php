<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
class Image extends BaseValidation
{
    public $rule = 'image';
}
