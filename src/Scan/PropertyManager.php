<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

use Hyperf\Context\ApplicationContext;

class PropertyManager
{
    protected static array $content = [];

    protected static array $notSimpleClass = [];

    public static function getAll(): array
    {
        return [static::$content, static::$notSimpleClass];
    }

    public static function setNotSimpleClass($className): void
    {
        $className = trim($className, '\\');
        static::$notSimpleClass[$className] = true;
    }

    /**
     * 设置类中字段的属性.
     */
    public static function setProperty(string $className, string $fieldName, Property $property): void
    {
        $className = trim($className, '\\');
        if (isset(static::$content[$className][$fieldName])) {
            return;
        }
        static::$content[$className][$fieldName] = $property;
    }

    /**
     * 获取类中字段的属性.
     * @param $className
     * @param $fieldName
     */
    public static function getProperty($className, $fieldName): ?Property
    {
        $className = trim($className, '\\');
        if (! isset(static::$content[$className][$fieldName])) {
            $di = ApplicationContext::getContainer();
            if ($di->has($className)) {
                $di->get(Scan::class)->scanClass($className);
                return self::getProperty($className, $fieldName);
            }
            return null;
        }
        return static::$content[$className][$fieldName];
    }


    public static function getPropertyByType($className, $type, bool $isSimpleType): array
    {
        $className = trim($className, '\\');
        if (! isset(static::$content[$className])) {
            return [];
        }
        $data = [];
        foreach (static::$content[$className] as $fieldName => $propertyArr) {
            /** @var Property $property */
            foreach ($propertyArr as $property) {
                if ($property->phpSimpleType == $type
                    && $property->isSimpleType == $isSimpleType
                ) {
                    $data[$fieldName] = $property;
                }
            }
        }
        return $data;
    }

    /**
     * @param $className
     * @return Property[]
     */
    public static function getPropertyAndNotSimpleType($className): array
    {
        $className = trim($className, '\\');
        if (! isset(static::$notSimpleClass[$className])) {
            return [];
        }
        $data = [];
        foreach (static::$content[$className] ?? [] as $fieldName => $property) {
            if (! $property->isSimpleType) {
                $data[$fieldName] = $property;
            }
        }
        return $data;
    }
}
