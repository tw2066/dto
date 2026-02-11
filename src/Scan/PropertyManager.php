<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

use Hyperf\Di\ReflectionManager;
use Hyperf\DTO\Annotation\JSONField;
use Hyperf\DTO\ApiAnnotation;
use Hyperf\DTO\DtoCommon;
use Hyperf\DTO\Exception\DtoException;

class PropertyManager
{
    private const MAX_SCAN_DEPTH = 100;

    protected static array $content = [];

    protected static array $notSimpleClass = [];

    private static array $scanClassArray = [];

    public function __construct(protected DtoCommon $dtoCommon, protected PropertyEnum $propertyEnum)
    {
    }

    public function getPropertyByClass(string $className): array
    {
        $this->scanClassProperties($className);
        return static::$content[$className] ?? [];
    }

    /**
     * 获取类中字段的属性.
     */
    public function getProperty(string $className, string $fieldName): ?Property
    {
        return $this->getPropertyByClass($className)[$fieldName] ?? null;
    }

    /**
     * @return Property[]
     */
    public function getPropertyAndNotSimpleType(string $className): array
    {
        if (! isset(static::$notSimpleClass[$className])) {
            return [];
        }
        return static::$notSimpleClass[$className];
    }

    public function scanClassProperties(string $className, int $depth = 0): void
    {
        if ($depth > self::MAX_SCAN_DEPTH) {
            throw new DtoException('DTO class nesting too deep (max ' . self::MAX_SCAN_DEPTH . "): {$className}");
        }

        $className = ltrim($className, '\\');
        if (in_array($className, self::$scanClassArray)) {
            return;
        }
        self::$scanClassArray[] = $className;

        $rc = ReflectionManager::reflectClass($className);
        $strNs = $rc->getNamespaceName();
        foreach ($rc->getProperties() ?? [] as $reflectionProperty) {
            $fieldName = $reflectionProperty->getName();
            $isSimpleType = true;
            $phpSimpleType = null;
            $propertyClassName = null;
            $arrSimpleType = null;
            $arrClassName = null;
            $type = $this->dtoCommon->getTypeName($reflectionProperty);

            // php简单类型
            if ($this->dtoCommon->isSimpleType($type)) {
                $phpSimpleType = $type;
            }
            // 数组类型
            $propertyEnum = $this->propertyEnum->get($type);
            if ($type == 'array') {
                $docblock = $reflectionProperty->getDocComment();
                $annotations = $this->dtoCommon->parseAnnotationsNew($rc, $reflectionProperty, $docblock);
                if (! empty($annotations)) {
                    // support "@var type description"
                    [$varType] = explode(' ', $annotations['var'][0]);
                    $varType = $this->dtoCommon->getFullNamespace($varType, $strNs);
                    // 数组类型
                    if ($this->dtoCommon->isArrayOfType($varType)) {
                        $isSimpleType = false;
                        $arrType = substr($varType, 0, -2);
                        // 数组的简单类型 eg: int[]  string[]
                        if ($this->dtoCommon->isSimpleType($arrType)) {
                            $arrSimpleType = $arrType;
                        } elseif (class_exists($arrType)) {
                            $arrClassName = $arrType;
                            $this->scanClassProperties($arrType, $depth + 1);
                        }
                    }
                }
            } elseif ($propertyEnum) {
                $isSimpleType = false;
            } elseif (class_exists($type)) {
                $this->scanClassProperties($type, $depth + 1);
                $isSimpleType = false;
                $propertyClassName = $type;
            }
            /** @var JSONField $JSONField */
            $JSONField = ApiAnnotation::getProperty($className, $fieldName, JSONField::class);
            $JSONFieldName = $JSONField?->name;

            $property = new Property();
            $property->phpSimpleType = $phpSimpleType;
            $property->isSimpleType = $isSimpleType;
            $property->arrSimpleType = $arrSimpleType;
            $property->arrClassName = $arrClassName ? trim($arrClassName, '\\') : null;
            $property->className = $propertyClassName ? trim($propertyClassName, '\\') : null;
            $property->enum = $propertyEnum;
            $property->alias = $JSONFieldName;
            $this->setProperty($className, $fieldName, $property);
        }
    }

    /**
     * 设置类中字段的属性.
     */
    private function setProperty(string $className, string $fieldName, Property $property): void
    {
        // 判断复杂类型 用于数据验证
        if (! $property->isSimpleType) {
            static::$notSimpleClass[$className][$fieldName] = $property;
        }
        static::$content[$className][$fieldName] = $property;
    }
}
