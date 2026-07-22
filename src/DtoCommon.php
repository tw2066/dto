<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use ReflectionParameter;
use ReflectionProperty;

class DtoCommon extends JsonMapper
{
    /**
     * 获取PHP类型.
     */
    public function getTypeName(ReflectionProperty|ReflectionParameter $rprop): string
    {
        if ($rprop->hasType()) {
            $rPropType = $rprop->getType();
            $propTypeName = $this->stringifyReflectionType($rPropType);
            if ($this->isSimpleType($propTypeName)) {
                return $propTypeName;
            }
            return '\\' . ltrim(explode('|', $propTypeName)[0], '\\');
        }
        return 'string';
    }

    public function isSimpleType(string $type): bool
    {
        return parent::isSimpleType($type);
    }

    public function getFullNamespace(?string $type, string $strNs): ?string
    {
        return parent::getFullNamespace($type, $strNs);
    }

    public function isArrayOfType(string $strType): bool
    {
        return parent::isArrayOfType($strType);
    }

    public function getSafeName(string $name): string
    {
        return parent::getSafeName($name);
    }
}
