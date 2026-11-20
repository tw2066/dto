<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

use Hyperf\Coroutine\Coroutine;
use Hyperf\Coroutine\Locker;
use Hyperf\Di\ReflectionManager;
use Hyperf\DTO\Annotation\JSONField;
use Hyperf\DTO\ApiAnnotation;
use Hyperf\DTO\DtoCommon;
use Hyperf\DTO\Exception\DtoException;
use ReflectionClass;
use ReflectionProperty;
use ReflectionUnionType;
use Throwable;

class PropertyManager
{
    private const MAX_SCAN_DEPTH = 100;

    protected static array $content = [];

    protected static array $notSimpleClass = [];

    private static array $scannedClasses = [];

    /**
     * 当前扫描调用栈中的类，用于循环引用提前返回。
     *
     * @var array<string, true>
     */
    private static array $scanningClasses = [];

    /**
     * 持有全局扫描锁的协程 ID；同一协程的嵌套扫描直接放行。
     */
    private static ?int $scanningOwnerCid = null;

    public function __construct(protected DtoCommon $dtoCommon, protected PropertyEnum $propertyEnum)
    {
    }

    public function getPropertyByClass(string $className): array
    {
        $className = ltrim($className, '\\');
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
        $className = ltrim($className, '\\');
        return static::$notSimpleClass[$className] ?? [];
    }

    public function scanClassProperties(string $className, int $depth = 0): void
    {
        $className = ltrim($className, '\\');
        $cid = Coroutine::id();
        if (self::$scanningOwnerCid === $cid) {
            // 当前协程已持有扫描锁，嵌套类直接扫描，避免重复加锁/死锁。
            $this->scanClassPropertiesInternal($className, $depth);
            return;
        }
        if (isset(self::$scannedClasses[$className])) {
            return;
        }

        $lockKey = 'dto.scan';
        // 全局扫描锁：首次扫描串行化，既避免读到半成品，也避免按类加锁的顺序死锁。
        while (! Locker::lock($lockKey)) {
            if (isset(self::$scannedClasses[$className])) {
                return;
            }
        }
        self::$scanningOwnerCid = $cid;
        try {
            $this->scanClassPropertiesInternal($className, $depth);
        } finally {
            self::$scanningOwnerCid = null;
            Locker::unlock($lockKey);
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

    /**
     * 扫描实现；调用方必须已持有全局扫描锁。
     */
    private function scanClassPropertiesInternal(string $className, int $depth): void
    {
        if ($depth > self::MAX_SCAN_DEPTH) {
            throw new DtoException('DTO class nesting too deep (max ' . self::MAX_SCAN_DEPTH . "): {$className}");
        }

        $className = ltrim($className, '\\');
        if (isset(self::$scannedClasses[$className]) || isset(self::$scanningClasses[$className])) {
            return;
        }
        self::$scanningClasses[$className] = true;

        try {
            $rc = ReflectionManager::reflectClass($className);
            foreach ($this->getClassProperties($rc) as $reflectionProperty) {
                $fieldName = $reflectionProperty->getName();
                [, $accessor, $type] = $this->dtoCommon->inspectPropertyMetadata($rc, $fieldName);
                $readUntypedPropertyAnnotations = $accessor !== null && ! $reflectionProperty->hasType()
                    && ($type === null || in_array($type, ['mixed', 'object', 'iterable'], true));
                // 原生联合类型无法确定唯一目标类：不能把首个成员写进 className，
                // 否则 wide/无类型 setter 时映射、验证和文档都会误用该成员。
                $declaredUnion = $reflectionProperty->getType() instanceof ReflectionUnionType;
                $unionDerived = $declaredUnion
                    && ($type === null || in_array($type, ['mixed', 'object', 'iterable'], true));
                if ($unionDerived) {
                    $type = null;
                } elseif (in_array($type, ['mixed', 'object', 'iterable'], true)) {
                    // 宽泛 setter 不能遮蔽属性上的 ArrayType/@var/原生类声明。
                    $type = $this->dtoCommon->getTypeName($reflectionProperty);
                }
                if ($type === null && ! $declaredUnion) {
                    $type = $this->dtoCommon->getTypeName($reflectionProperty);
                }
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
                $property->union = $unionDerived;
                $inheritedPrivate = $reflectionProperty->isPrivate()
                    && $reflectionProperty->getDeclaringClass()->getName() !== $className;
                $this->setProperty($className, $fieldName, $property, $inheritedPrivate);
            }
            // 元数据全部填充完成后才发布，其他协程见到的内容始终完整。
            self::$scannedClasses[$className] = true;
        } catch (Throwable $exception) {
            // 失败时回滚；scannedClasses 只在成功后置位，后续请求可安全重试。
            unset(static::$content[$className], static::$notSimpleClass[$className]);
            throw $exception;
        } finally {
            unset(self::$scanningClasses[$className]);
        }
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
