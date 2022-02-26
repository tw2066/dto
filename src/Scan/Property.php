<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

class Property
{
    public bool $isSimpleType;

    public ?string $phpType = null;

    /**
     * 1. $phpType为数组时,对应的类型  eg: int[]  Hyperf\DTO\Scan\Property[]
     * 2. 对应一般类  eg:Hyperf\DTO\Scan\Property.
     */
    public ?string $className = null;
}
