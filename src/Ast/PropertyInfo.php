<?php

declare(strict_types=1);

namespace Hyperf\DTO\Ast;

class PropertyInfo
{
    /**
     * 属性名称.
     */
    public string $propertyName = '';

    /**
     * get方法名称.
     */
    public string $getMethodName = '';

    /**
     * 别名 通过JSONField生成.
     */
    public string $alias = '';

    /**
     * ArrayType 注解声明的数组元素类型.
     */
    public string $arrayType = '';

    /**
     * 生成json数组的key.
     */
    public string $jsonArrKey = '';

    /**
     * 是否生成json数组的key.
     */
    public bool $isJsonSerialize = true;
}
