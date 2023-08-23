<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Di\Annotation\AnnotationCollector;

class ApiAnnotation
{
    /**
     * 获取属性.
     * @param mixed $className
     * @param mixed $propertyName
     * @param mixed $annotationClassName
     * @return null|object $annotationClassName
     */
    public static function getProperty($className, $propertyName, $annotationClassName): ?object
    {
        $className = ltrim($className, '\\');
        $propertyAnnotations = AnnotationCollector::getClassPropertyAnnotation($className, $propertyName);
        return $propertyAnnotations[$annotationClassName] ?? null;
    }

    public static function getClassProperty($className, $propertyName): array
    {
        $className = ltrim($className, '\\');
        return AnnotationCollector::getClassPropertyAnnotation($className, $propertyName) ?? [];
    }

    public static function classMetadata($className)
    {
        $className = ltrim($className, '\\');
        return AnnotationCollector::list()[$className]['_c'] ?? [];
    }

    public static function methodMetadata($className)
    {
        $className = ltrim($className, '\\');
        return AnnotationCollector::list()[$className]['_m'] ?? [];
    }

    public static function propertyMetadata($className)
    {
        $className = ltrim($className, '\\');
        return AnnotationCollector::list()[$className]['_p'] ?? [];
    }
}
