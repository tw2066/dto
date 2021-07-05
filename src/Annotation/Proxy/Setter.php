<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Proxy;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_CLASS)]
class Setter extends AbstractAnnotation
{
}
