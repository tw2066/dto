<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;
use Hyperf\DTO\Type\PhpType;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ArrayType extends AbstractAnnotation
{
    public string $value;

    public function __construct(string|PhpType $value)
    {
        if ($value instanceof PhpType) {
            $this->value = $value->getValue();
        } else {
            $this->value = $value;
        }
    }
}
