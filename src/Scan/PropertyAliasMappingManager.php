<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

class PropertyAliasMappingManager
{
    protected static array $content = [];

    protected static array $aliasMappingClassname = [];
    protected static bool $isAliasMapping = false;

    public static function setAliasMapping(string $classname, string $alias, string $propertyName): void
    {
        static::$content[$alias] = $propertyName;
        static::$aliasMappingClassname[$classname] = true;
        static::$isAliasMapping = true;
    }

    public static function getAliasMapping(string $alias): ?string
    {
        return static::$content[$alias] ?? null;
    }

    public static function isAliasMappingClassname(string $classname): bool
    {
        return isset(static::$aliasMappingClassname[$classname]);
    }

    public static function isAliasMapping()
    {
        return static::$isAliasMapping;
    }
}
