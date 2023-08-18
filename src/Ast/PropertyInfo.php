<?php

declare(strict_types=1);

namespace Hyperf\DTO\Ast;

class PropertyInfo
{
    /**
     * 属性名称
     * @var string
     */
    public string $propertyName = '';

    /**
     * get方法名称
     * @var string
     */
    public string $getMethodName = '';

    /**
     * 别名 通过JSONField生成
     * @var string
     */
    public string $alias = '';

    /**
     * 生成json数组的key
     * @var string
     */
    public string $jsonArrKey = '';
    /**
     * 是否生成json数组的key
     * @var bool
     */
    public bool $isJsonSerialize = true;

//    /**
//     * json数组的key默认返回值
//     * @var mixed|null
//     */
//    public mixed $jsonSerializeDefaultValue = null;
}
