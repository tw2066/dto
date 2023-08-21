<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Di\Annotation\AnnotationCollector as BaseAnnotationCollector;
use Hyperf\Di\Annotation\MultipleAnnotation;
use Hyperf\DTO\Annotation\JSONField;
use Hyperf\DTO\Annotation\Validation\BaseValidation;

class AnnotationCollector extends BaseAnnotationCollector
{
    public static function replaceProperty(string $className, string $aliasProperty, string $property): void
    {
        $aliasAnnotationArr = $annotationArr = static::$container[$className]['_p'][$property];
        foreach ($annotationArr as $classname => $annotation) {
            // 删除别名JSONField注解
            if ($annotation instanceof JSONField) {
                unset($aliasAnnotationArr[$classname]);
            }
            // 删除原校验注解
            elseif ($annotation instanceof MultipleAnnotation && $annotation->toAnnotations()[0] instanceof BaseValidation) {
                unset($annotationArr[$classname]);
            }
        }
        static::$container[$className]['_p'][$aliasProperty] = $aliasAnnotationArr;
        static::$container[$className]['_p'][$property] = $annotationArr;
    }
}
