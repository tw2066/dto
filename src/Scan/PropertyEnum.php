<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

use BackedEnum;
use ReflectionEnum;

use ReflectionException;
use function Hyperf\Collection\collect;

class PropertyEnum
{
    /**
     * PHP 内置类型与伪类型，不可能是枚举类，也不应触发类自动加载.
     */
    private const BUILTIN_TYPES = [
        'array', 'bool', 'boolean', 'callable', 'double', 'false', 'float',
        'int', 'integer', 'iterable', 'mixed', 'never', 'null', 'object',
        'parent', 'resource', 'self', 'static', 'string', 'true', 'void',
    ];

    /**
     * 返回的类型.
     */
    public ?string $backedType = null;

    /**
     * 名称.
     */
    public ?string $className = null;

    /**
     * 枚举类 value列表.
     */
    public ?array $valueList = null;

    public static function isBuiltinType(string $type): bool
    {
        return in_array(strtolower(ltrim($type, '\\')), self::BUILTIN_TYPES, true);
    }

    public function get(string $className): ?PropertyEnum
    {
        if (static::isBuiltinType($className)) {
            return null;
        }
        if (! is_subclass_of($className, BackedEnum::class)) {
            return null;
        }
        $propertyEnum = new static();
        try {
            /* @phpstan-ignore-next-line */
            $rEnum = new ReflectionEnum($className);
            $propertyEnum->backedType = (string) $rEnum->getBackingType();
        } catch (ReflectionException) {
            $propertyEnum->backedType = 'string';
        }
        $propertyEnum->className = trim($className, '\\');
        $propertyEnum->valueList = collect($className::cases())->map(fn ($v) => $v->value)->all();
        return $propertyEnum;
    }
}
