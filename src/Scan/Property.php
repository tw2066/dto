<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

class Property
{
    public bool $isSimpleType;

    public ?string $phpType = null;

    public ?string $className = null;

    /**
     * type为数组时,对应的类型  eg: int[]
     * @var string|null
     */
    public ?string $arrayType = null;
}
