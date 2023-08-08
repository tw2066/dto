<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

class PropertyAliasMappingManager
{
    protected static array $content = [];

    protected static bool $isAliasMapping = false;

    public static function setAliasMapping(string $className, string $alias, string $propertyName): void
    {
        $className = trim($className, '\\');
        static::$content[$className]['alias'][$alias] = $propertyName;
        static::$content[$className]['property'][$propertyName] = $alias;
        static::$isAliasMapping = true;
    }

    public static function getAliasMapping(string $className, string $alias): ?string
    {
        $className = trim($className, '\\');
        return static::$content[$className]['alias'][$alias] ?? null;
    }

    public static function getPropertyMapping(string $className, string $propertyName): ?string
    {
        $className = trim($className, '\\');
        return static::$content[$className]['property'][$propertyName] ?? null;
    }

    public static function isAliasMapping(): bool
    {
        return static::$isAliasMapping;
    }
}
