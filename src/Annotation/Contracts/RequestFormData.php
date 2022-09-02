<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Contracts;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_PARAMETER)]
class RequestFormData extends AbstractAnnotation
{
}
