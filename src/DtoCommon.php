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

    public function isSimpleType($type)
    {
        return parent::isSimpleType($type);
    }

    public function getFullNamespace($type, $strNs)
    {
        return parent::getFullNamespace($type, $strNs);
    }

    public function isArrayOfType($strType)
    {
        return parent::isArrayOfType($strType);
    }

    public function getSafeName($name)
    {
        return parent::getSafeName($name);
    }
}
