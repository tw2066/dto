<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Doctrine\Common\Annotations\AnnotationReader;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\ReflectionManager;
use ReflectionAttribute;

class ApiAnnotation
{
    public static function propertyMetadata($className, $propertyName): array
    {
        $reflectMethod = ReflectionManager::reflectProperty($className, $propertyName);
        $reader = new AnnotationReader();
        return $reader->getPropertyAnnotations($reflectMethod);
    }

    /**
     * @param $className
     * @param $propertyName
     * @param $annotationClassName
     * @return null|object $annotationClassName
     */
    public static function property($className, $propertyName, $annotationClassName): ?object
    {
        $reflectMethod = ReflectionManager::reflectProperty($className, $propertyName);
        /** @var ReflectionAttribute $ra */
        $ra = $reflectMethod->getAttributes($annotationClassName)[0] ?? null;
        if ($ra) {
            return $ra->newInstance();
        }
        return null;
    }

    /**
     * @param $className
     * @param $propertyName
     * @return array
     */
    public static function propertyArray($className, $propertyName): array
    {
        $reflectMethod = ReflectionManager::reflectProperty($className, $propertyName);
        $raArr = $reflectMethod->getAttributes() ?? [];
        $arr = [];
        /** @var ReflectionAttribute $ra */
        foreach ($raArr as $ra) {
            $arr[] = $ra->newInstance();
        }
        return $arr;
    }

    public static function methodArray($className, $methodName): array
    {
        $reflectMethod = ReflectionManager::reflectMethod($className, $methodName);
        $raArr = $reflectMethod->getAttributes() ?? [];
        $arr = [];
        /** @var ReflectionAttribute $ra */
        foreach ($raArr as $ra) {
            $arr[] = $ra->newInstance();
        }
        return $arr;
    }

    public static function classMetadata($className)
    {
        return AnnotationCollector::list()[$className]['_c'] ?? [];
    }

    public static function methodMetadata($className)
    {
        return AnnotationCollector::list()[$className]['_m'] ?? [];
    }
}