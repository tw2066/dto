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

    /**
     * @return array<string, string> PHP 属性名到外部别名
     */
    public function getExternalKeyMap(string $className): array
    {
        $result = [];
        foreach ($this->getPropertyByClass($className) as $fieldName => $property) {
            if ($property->alias !== null && $property->alias !== $fieldName) {
                $result[$fieldName] = $property->alias;
            }
        }
        return $result;
    }

    public function getExternalFieldPath(string $className, string $fieldPath): string
    {
        $currentClass = ltrim($className, '\\');
        $result = [];
        foreach (explode('.', $fieldPath) as $segment) {
            if ($currentClass === null || $segment === '*' || ctype_digit($segment)) {
                $result[] = $segment;
                continue;
            }
            $property = $this->getProperty($currentClass, $segment);
            if ($property === null) {
                $currentClass = null;
                $result[] = $segment;
                continue;
            }
            $result[] = $property->alias ?? $segment;
            $currentClass = $property->className ?? $property->arrClassName;
        }
        return implode('.', $result);
    }

    /**
     * @internal 统一生成验证器使用的外部属性名映射。
     */
    public function getExternalAttributes(string $className, array $ruleKeys): array
    {
        $attributes = $this->getExternalKeyMap($className);
        foreach ($ruleKeys as $ruleKey) {
            $externalPath = $this->getExternalFieldPath($className, $ruleKey);
            if ($externalPath !== $ruleKey) {
                $attributes[$ruleKey] = $externalPath;
            }
        }
        return $attributes;
    }

    /**
     * @internal 将旧别名/customKey 路径归一为 PHP 属性路径。
     */
    public function getCanonicalFieldPath(string $className, string $fieldPath): string
    {
        $currentClass = ltrim($className, '\\');
        $result = [];
        foreach (explode('.', $fieldPath) as $segment) {
            if ($currentClass === null || $segment === '*' || ctype_digit($segment)) {
                $result[] = $segment;
                continue;
            }
            $properties = $this->getPropertyByClass($currentClass);
            $property = $properties[$segment] ?? null;
            $canonicalSegment = $segment;
            if ($property === null) {
                foreach ($properties as $fieldName => $candidate) {
                    if ($candidate->alias === $segment) {
                        $property = $candidate;
                        $canonicalSegment = $fieldName;
                        break;
                    }
                }
            }
            if ($property === null) {
                $currentClass = null;
                $result[] = $segment;
                continue;
            }
            $result[] = $canonicalSegment;
            $currentClass = $property->className ?? $property->arrClassName;
        }
        return implode('.', $result);
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
                [, , $type] = $this->dtoCommon->inspectPropertyMetadata($rc, $fieldName);
                if (in_array($type, ['mixed', 'object', 'iterable'], true)) {
                    // 宽泛 setter 不能遮蔽属性上的 ArrayType/@var/原生类声明。
                    $type = $this->dtoCommon->getTypeName($reflectionProperty);
                }
                $type ??= $this->dtoCommon->getTypeName($reflectionProperty);
                $type = $this->dtoCommon->removeNullable($type);

                if ($type === 'array') {
                    // setter 只声明 array 或没有 @param 时，属性级 #[ArrayType]/@var
                    // 仍是元素类型的唯一来源，不能因访问器是方法而跳过。
                    $annotations = $this->dtoCommon->parseAnnotationsNew(
                        $rc,
                        $reflectionProperty,
                        $reflectionProperty->getDocComment()
                    );
                    if (! empty($annotations)) {
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
            } elseif (class_exists($arrType)) {
                $property->arrClassName = trim($arrType, '\\');
                $this->scanClassProperties($arrType, $depth + 1);
            }
            return $property;
        }

        $property->enum = $this->propertyEnum->get($type);
        if ($property->enum) {
            $property->isSimpleType = false;
        } elseif (class_exists($type)) {
            $property->isSimpleType = false;
            $property->className = trim($type, '\\');
            $this->scanClassProperties($type, $depth + 1);
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
