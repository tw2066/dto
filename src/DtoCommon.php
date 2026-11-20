<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

class DtoCommon extends JsonMapper
{
    /**
     * 获取PHP类型
     */
    public function getTypeName(ReflectionParameter|ReflectionProperty $rprop): string
    {
        if ($rprop->hasType()) {
            $rPropType = $rprop->getType();
            $propTypeName = $this->stringifyReflectionType($rPropType);
            if ($this->isSimpleType($propTypeName)) {
                return $propTypeName;
            }
            // 与 Hyperf AbstractCallableDefinitionCollector 一致：联合类型取第一个成员。
            // 首成员是内置类型时不要加前导反斜杠，交给 createPropertyFromType 按单类型处理。
            $parts = explode('|', $propTypeName);
            $first = $parts[0];
            if ($first === 'null' && isset($parts[1])) {
                $first = $parts[1];
            }
            if ($first === 'true' || $first === 'false') {
                return 'bool';
            }
            if ($this->isSimpleType($first)) {
                return $first;
            }
            return '\\' . ltrim($first, '\\');
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

    /**
     * 复用映射器的属性访问器和类型判断，避免扫描阶段另维护一套规则
     *
     * @return array{bool, null|ReflectionMethod|ReflectionProperty, null|string, bool}
     */
    public function inspectPropertyMetadata(ReflectionClass $rc, string $name): array
    {
        return $this->inspectProperty($rc, $name);
    }

    public function removeNullable(?string $type): ?string
    {
        return parent::removeNullable($type);
    }
}
