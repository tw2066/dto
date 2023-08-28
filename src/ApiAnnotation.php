<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Di\Annotation\AnnotationCollector;

class ApiAnnotation
{
    /**
     * 获取属性.
     * @param string $className
     * @param string $propertyName
     * @param string $annotationClassName
     * @return null|object $annotationClassName
     */
    public static function getProperty(string $className,string $propertyName,string $annotationClassName): ?object
    {
        $propertyAnnotations = AnnotationCollector::getClassPropertyAnnotation($className, $propertyName);
        return $propertyAnnotations[$annotationClassName] ?? null;
    }

    public static function getClassProperty(string $className,string $propertyName): array
    {
        return AnnotationCollector::getClassPropertyAnnotation($className, $propertyName) ?? [];
    }

    public static function classMetadata(string $className)
    {
        return AnnotationCollector::list()[$className]['_c'] ?? [];
    }

    public static function methodMetadata(string $className)
    {
        return AnnotationCollector::list()[$className]['_m'] ?? [];
    }

    public static function propertyMetadata(string $className)
    {
        return AnnotationCollector::list()[$className]['_p'] ?? [];
    }
}
