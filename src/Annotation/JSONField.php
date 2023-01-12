<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_PROPERTY)]
class JSONField extends AbstractAnnotation
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
