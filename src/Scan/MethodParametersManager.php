<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

class MethodParametersManager
{
    public static array $content = [];

    public static function setContent(string $className, string $methodName, string $paramName, MethodParameter $method)
    {
        $className = trim($className, '\\');
        if (isset(static::$content[$className . $methodName . $paramName])) {
            return;
        }
        static::$content[$className . $methodName . $paramName] = $method;
    }

    public static function getMethodParameter(string $className, string $methodName, string $paramName): ?MethodParameter
    {
        $className = trim($className, '\\');
        if (! isset(static::$content[$className . $methodName . $paramName])) {
            return null;
        }
        return static::$content[$className . $methodName . $paramName];
    }
}
