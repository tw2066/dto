<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

class ValidationManager
{
    protected static array $content = [];

    public static function setRule($className, $fieldName, $rule)
    {
        $className = trim($className, '\\');
        static::$content[$className]['rule'][$fieldName] = $rule;
    }

    public static function setMessages($className, $key, $messages)
    {
        $className = trim($className, '\\');
        static::$content[$className]['messages'][$key] = $messages;
    }

    public static function setAttributes($className, $fieldName, $value)
    {
        $className = trim($className, '\\');
        static::$content[$className]['attributes'][$fieldName] = $value;
    }

    public static function getData($className)
    {
        $className = trim($className, '\\');
        return static::$content[$className] ?? null;
    }
}
