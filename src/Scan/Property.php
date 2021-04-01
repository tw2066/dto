<?php

namespace Hyperf\DTO\Scan;

class Property
{
    public bool $isSimpleType;

    public string $type;

    public ?string $className;
}