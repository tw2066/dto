<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

class Property
{
    public bool $isSimpleType;

    public string $type;

    public ?string $className;
}
