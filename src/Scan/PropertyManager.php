<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

use Hyperf\Di\ReflectionManager;
use Hyperf\DTO\Annotation\JSONField;
use Hyperf\DTO\ApiAnnotation;
use Hyperf\DTO\DtoCommon;
use Hyperf\DTO\Exception\DtoException;
use ReflectionClass;
use ReflectionProperty;

class PropertyManager
{
    private const MAX_SCAN_DEPTH = 100;

    protected static array $content = [];

    protected static array $notSimpleClass = [];

    private static array $scannedClasses = [];

    public function __construct(protected DtoCommon $dtoCommon, protected PropertyEnum $propertyEnum)
    {
    }

    public function getPropertyByClass(string $className): array
    {
        $this->scanClassProperties($className);
        return static::$content[$className] ?? [];
    }

    public function getProperty(string $className, string $fieldName): ?Property
    {
        return $this->getPropertyByClass($className)[$fieldName] ?? null;
    }

    /**
     * @return Property[]
     */
    public function getPropertyAndNotSimpleType(string $className): array
    {
        return static::$notSimpleClass[$className] ?? [];
    }

    public function scanClassProperties(string $className, int $depth = 0): void
    {
        if ($depth > self::MAX_SCAN_DEPTH) {
            throw new DtoException('DTO class nesting too deep (max ' . self::MAX_SCAN_DEPTH . "): {$className}");
        }

        $className = ltrim($className, '\\');
        if (isset(self::$scannedClasses[$className])) {
            return;
        }
        self::$scannedClasses[$className] = true;

        try {
            $rc = ReflectionManager::reflectClass($className);
            foreach ($this->getClassProperties($rc) as $reflectionProperty) {
                $fieldName = $reflectionProperty->getName();
                [, $accessor, $type] = $this->dtoCommon->inspectPropertyMetadata($rc, $fieldName);
                $readUntypedPropertyAnnotations = $accessor !== null && ! $reflectionProperty->hasType()
                    && ($type === null || in_array($type, ['mixed', 'object', 'iterable'], true));
                if (in_array($type, ['mixed', 'object', 'iterable'], true)) {
                    // 宽泛 setter 不能遮蔽属性上的 ArrayType/@var/原生类声明。
                    $type = $this->dtoCommon->getTypeName($reflectionProperty);
                }
                $type ??= $this->dtoCommon->getTypeName($reflectionProperty);
                $type = $this->dtoCommon->removeNullable($type);

                if ($type === 'array' || $readUntypedPropertyAnnotations) {
                    // 可写访问器未提供嵌套类型时，属性级 #[ArrayType]/@var
                    // 仍是元素类型的唯一来源；不可写属性不能只参与验证。
                    $annotations = $this->dtoCommon->parseAnnotationsNew(
                        $rc,
                        $reflectionProperty,
                        $reflectionProperty->getDocComment()
                    );
                    if (! empty($annotations['var'])) {
                        [$type] = explode(' ', $annotations['var'][0]);
                    }
                }

                $property = $this->createPropertyFromType(
                    $type,
                    $rc->getNamespaceName(),
                    $this->getJsonFieldName($className, $fieldName, $reflectionProperty),
                    $depth
                );
                $inheritedPrivate = $reflectionProperty->isPrivate()
                    && $reflectionProperty->getDeclaringClass()->getName() !== $className;
                $this->setProperty($className, $fieldName, $property, $inheritedPrivate);
            }
        } catch (\Throwable $exception) {
            // 失败时回滚，避免「已扫描」标记把截断元数据留给后续请求。
            unset(self::$scannedClasses[$className], static::$content[$className], static::$notSimpleClass[$className]);
            throw $exception;
        }
    }

    /**
     * @internal 由同一套类型信息生成属性描述，供声明属性和 setter-only 字段复用
     */
    public function createPropertyFromType(?string $type, string $namespace, ?string $alias = null, int $depth = 0): Property
    {
        $property = new Property();
        $property->alias = $alias;
        if ($type === null) {
            return $property;
        }

        $type = $this->dtoCommon->removeNullable($type);
        $type = $this->dtoCommon->getFullNamespace($type, $namespace);
        if ($this->dtoCommon->isSimpleType($type)) {
            $property->phpSimpleType = $type;
        }

        $arrayDepth = 0;
        $arrType = $type;
        while ($this->dtoCommon->isArrayOfType($arrType)) {
            ++$arrayDepth;
            $arrType = substr($arrType, 0, -2);
        }
        if ($arrayDepth > 0) {
            $property->isSimpleType = false;
            $property->phpSimpleType = 'array';
            $property->arrayDepth = $arrayDepth;
            if ($this->dtoCommon->isSimpleType($arrType)) {
                $property->arrSimpleType = $arrType;
            } elseif (! PropertyEnum::isBuiltinType($arrType) && class_exists($arrType)) {
                $property->arrClassName = trim($arrType, '\\');
                $this->scanClassProperties($arrType, $depth + 1);
            } elseif (! PropertyEnum::isBuiltinType($arrType) && interface_exists($arrType)) {
                // 接口不可实例化，只记录名称，验证阶段按 classMap 解析具体类，不递归扫描。
                $property->arrInterfaceName = trim($arrType, '\\');
            }
            return $property;
        }

        $property->enum = $this->propertyEnum->get($type);
        if ($property->enum) {
            $property->isSimpleType = false;
        } elseif (! PropertyEnum::isBuiltinType($type) && class_exists($type)) {
            $property->isSimpleType = false;
            $property->className = trim($type, '\\');
            $this->scanClassProperties($type, $depth + 1);
        } elseif (! PropertyEnum::isBuiltinType($type) && interface_exists($type)) {
            $property->isSimpleType = false;
            $property->interfaceName = trim($type, '\\');
        }
        return $property;
    }

    private function setProperty(string $className, string $fieldName, Property $property, bool $inheritedPrivate): void
    {
        // 父类 private 仅为 bIgnoreVisibility 映射保留，不参与子类递归校验。
        if (! $property->isSimpleType && ! $inheritedPrivate) {
            static::$notSimpleClass[$className][$fieldName] = $property;
        }
        static::$content[$className][$fieldName] = $property;
    }

    /**
     * @return array<string, ReflectionProperty>
     */
    private function getClassProperties(ReflectionClass $rc): array
    {
        $properties = [];
        $class = $rc;
        do {
            foreach ($class->getProperties() as $property) {
                $properties[$property->getName()] ??= $property;
            }
        } while ($class = $class->getParentClass());
        return $properties;
    }

    private function getJsonFieldName(string $className, string $fieldName, ReflectionProperty $property): ?string
    {
        /** @var null|JSONField $annotation */
        $annotation = ApiAnnotation::getProperty($className, $fieldName, JSONField::class);
        if ($annotation !== null) {
            return $annotation->name;
        }
        $attribute = $property->getAttributes(JSONField::class)[0] ?? null;
        return $attribute?->newInstance()->name;
    }
}
