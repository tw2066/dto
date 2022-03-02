<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

class Property
{
    public bool $isSimpleType = true;

    //             $type == 'string'
    //            || $type == 'boolean' || $type == 'bool'
    //            || $type == 'integer' || $type == 'int'
    //            || $type == 'double' || $type == 'float'
    //            || $type == 'array' || $type == 'object'
    public ?string $phpSimpleType = null;

    public ?string $className = null;

    public ?string $arrClassName = null;

    public ?string $arrSimpleType = null;

    public function isSimpleArray(): bool
    {
        if ($this->isSimpleType == true && $this->phpSimpleType == 'array') {
            return true;
        }
        return false;
    }

    public function isSimpleTypeArray(): bool
    {
        if ($this->isSimpleType == false && $this->phpSimpleType == 'array' && $this->arrSimpleType != null) {
            return true;
        }
        return false;
    }

    public function isClassArray(): bool
    {
        if ($this->isSimpleType == false && $this->phpSimpleType == 'array' && $this->arrClassName != null) {
            return true;
        }
        return false;
    }
}
