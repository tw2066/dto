<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

class Property
{
    /**
     * 是否为简单类型.
     */
    public bool $isSimpleType = true;

    /**
     * PHP简单类型.
     * @var null|string 'string' 'boolean' 'bool' 'integer' 'int' 'double' 'float' 'array' 'object'
     */
    public ?string $phpSimpleType = null;

    /**
     * 普通类名称（仅可实例化类，接口记录在 interfaceName）.
     */
    public ?string $className = null;

    /**
     * 接口名称（声明类型为 interface 时记录，验证阶段按 classMap 解析具体类）.
     */
    public ?string $interfaceName = null;

    /**
     * 数组 中 复杂 类的名称.
     */
    public ?string $arrClassName = null;

    /**
     * 数组元素为接口时的接口名称（arrClassName 只保留可实例化类）.
     */
    public ?string $arrInterfaceName = null;

    /**
     * 数组 中 简单类型  eg: int[]  string[].
     */
    public ?string $arrSimpleType = null;

    /**
     * 数组嵌套维度，Child[] 为 1，Child[][] 为 2.
     */
    public int $arrayDepth = 0;

    /**
     * 枚举类.
     */
    public ?PropertyEnum $enum = null;

    /**
     * 别名.
     */
    public ?string $alias = null;

    public function isSimpleArray(): bool
    {
        if ($this->isSimpleType && $this->phpSimpleType == 'array') {
            return true;
        }
        return false;
    }

    public function isSimpleTypeArray(): bool
    {
        if (! $this->isSimpleType && $this->phpSimpleType == 'array' && $this->arrSimpleType != null) {
            return true;
        }
        return false;
    }

    public function isClassArray(): bool
    {
        if (! $this->isSimpleType && $this->phpSimpleType == 'array'
            && ($this->arrClassName != null || $this->arrInterfaceName != null)) {
            return true;
        }
        return false;
    }
}
