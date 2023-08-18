<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;
use Hyperf\DTO\Type\Convert;

#[Attribute(Attribute::TARGET_CLASS)]
class Dto extends AbstractAnnotation
{
//    public ?Convert $requestConvert = null;

    public function __construct(
        public ?Convert $responseConvert = null,
    ) {
    }
}
