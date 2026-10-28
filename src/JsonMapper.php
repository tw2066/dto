<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Di\ReflectionManager;
use Hyperf\DTO\Annotation\ArrayType;
use Hyperf\DTO\Exception\DtoException;
use Hyperf\DTO\Mapping\MappingContext;
use Hyperf\DTO\Mapping\ResolvedInput;
use Hyperf\DTO\Scan\PropertyEnum;
use Hyperf\DTO\Scan\PropertyManager;
use Hyperf\DTO\Type\PhpType;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\Types\AbstractList;
use phpDocumentor\Reflection\Types\Compound;
use phpDocumentor\Reflection\Types\Context;
use phpDocumentor\Reflection\Types\ContextFactory;
use phpDocumentor\Reflection\Types\Mixed_;
use phpDocumentor\Reflection\Types\Null_;
use phpDocumentor\Reflection\Types\Nullable;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * DTO 输入映射内核。
 *
 * 普通 map 按类级字段计划直接读取输入并完成类型转换，不复制整棵输入树。
 * Valid/RPC 才通过 resolveInput() 物化一次 ResolvedInput，供验证和后续映射共用。
 * 实例缓存只保存类元数据；请求级预算保存在
 * MappingContext 中，canonical 输入和动态类型决策保存在 ResolvedInput 中，
 * 避免协程间串扰。
 */
class JsonMapper extends \JsonMapper
{
    /** @var array<string, array> 反射方法与属性（含父类 private 属性），供 inspectProperty 复用 */
    private array $classMetadata = [];

    /** @var array<string, array> 每个 DTO 类的字段、候选键、访问器和类型计划，不含请求数据 */
    private array $mappingPlans = [];

    /** bIgnoreVisibility 改变时，已缓存的访问器计划必须整体重建 */
    private bool $mappingPlanVisibility = false;

    private ?PropertyManager $propertyManager = null;

    private ?DocBlockFactory $docBlockFactory = null;

    private ?ContextFactory $contextFactory = null;

    /** @var array<string, Context> 按属性声明类缓存命名空间上下文，避免重复读取源文件 */
    private array $docBlockContexts = [];

    /**
     * 普通映射入口：每次公开调用创建独立预算，嵌套对象共享该预算。
     * 支持对象/数组输入以及类名字符串目标，行为与上游 JsonMapper 对齐。
     */
    public function map($json, $object)
    {
        if ($this->bEnforceMapType && ! is_object($json)) {
            throw new \InvalidArgumentException(
                'JsonMapper::map() requires first argument to be an object'
                . ', ' . gettype($json) . ' given.'
            );
        }
        if (! is_object($object) && (! is_string($object) || ! class_exists($object))) {
            throw new \InvalidArgumentException(
                'JsonMapper::map() requires second argument to '
                . 'be an object or existing class name'
                . ', ' . gettype($object) . ' given.'
            );
        }
        if (is_string($object)) {
            $object = $this->createInstance($object);
        }
        return $this->mapWithContext($json, $object, new MappingContext());
    }

    /**
     * @internal DTO 数组中的多个根节点共享同一个请求级预算。
     */
    public function mapWithContext(mixed $json, object $object, MappingContext $context): object
    {
        return $this->mapNode($json, $object, $context, 0, '', false);
    }

    /**
     * @internal 仅为验证或 RPC DTO 映射物化 canonical 输入；普通 map 不调用。
     */
    public function resolveInput(mixed $json, string $className, ?MappingContext $context = null): ResolvedInput
    {
        $className = ltrim($className, '\\');
        $context ??= new MappingContext();
        // 路径 => 实际类型：动态 classMap 只看原始输入，映射阶段不再二次分派。
        $mappedTypes = [];
        $data = $this->resolveData($className, $json, $context, 0, '', $mappedTypes);
        return new ResolvedInput($className, $data, $context, $mappedTypes);
    }

    /**
     * @internal 直接消费 resolveInput() 的结果，不重新解析别名或嵌套输入。
     * resolved=true 会复用 canonical 键、已计算的键预算和 mappedTypes。
     */
    public function mapResolvedInput(ResolvedInput $input, object $object): object
    {
        return $this->mapNode($input->data, $object, $input->context, 0, '', true, $input->mappedTypes);
    }

    /**
     * 数组元素映射：所有根元素共享一个 MappingContext，防止通过大批量数组
     * 绕过整棵树的累计键数限制；各元素仍走同一套类级计划。
     */
    public function mapArray(
        array|object $json, mixed $array, ?string $class = null, string $parent_key = ''
    ): mixed {
        return $this->mapArrayInternal(
            $json,
            $array,
            $class,
            $parent_key,
            new MappingContext(),
            0,
            '',
            false
        );
    }

    /**
     * 映射单个 DTO 节点。
     *
     * $resolved=false：输入为原始外部键，计入节点预算并解析别名/兼容变体。
     * $resolved=true：输入已由 resolveInput() 处理，只按 PHP 属性名读取，
     * 不重复计入预算，也不再执行键规范化。$mappedTypes 仅在该模式使用。
     */
    private function mapNode(
        mixed $json,
        object $object,
        MappingContext $context,
        int $depth,
        string $path,
        bool $resolved,
        array $mappedTypes = [],
    ): object {
        $className = get_class($object);
        $plan = $this->getMappingPlan($className);
        $input = $this->inputArray($json, $className);
        if (! $resolved) {
            $context->enterNode(count($input), $className, $depth);
        }

        $providedProperties = [];
        // 常规输入按字段主键一次读取；主键未传入时才扫描输入查找兼容键。
        foreach ($plan['fields'] as $index => $field) {
            if ($resolved) {
                $key = array_key_exists($field['name'], $input) ? $field['name'] : null;
            } else {
                $primaryKey = $field['primaryKey'];
                $key = array_key_exists($primaryKey, $input)
                    ? $primaryKey
                    : $this->selectFieldKey($field, $plan, $input, $index);
            }
            if ($key === null) {
                continue;
            }
            $providedProperties[$field['name']] = true;
            $this->writeField($object, $plan, $index, $input[$key], $context, $depth, $path, $resolved, $mappedTypes);
        }

        $unknown = [];
        $needsUnknownHandling = $this->bExceptionOnUndefinedProperty
            || $this->undefinedPropertyHandler !== null;
        // 每个已选字段消费一个不同的输入键；数量相等时不存在未知键。
        if ($needsUnknownHandling && count($input) !== count($providedProperties)) {
            if ($resolved) {
                $unknown = array_diff_key($input, $plan['propertyKeys']);
            } else {
                foreach ($input as $key => $value) {
                    $index = $plan['directMap'][$key] ?? null;
                    $known = $index !== null || $this->resolveFieldIndex((string) $key, $plan) !== null;
                    if (! $known) {
                        $unknown[$key] = $value;
                    }
                }
            }
        }
        if ($unknown !== []) {
            $this->handleUnknownProperties(
                $object,
                $plan,
                $unknown,
                $providedProperties,
                $context,
                $depth,
                $path,
            );
        }

        $rc = $plan['reflection'];
        if ($this->bExceptionOnMissingData) {
            $this->checkMissingData($providedProperties, $rc);
        }
        if ($this->bRemoveUndefinedAttributes) {
            $this->removeUndefinedAttributes($object, $providedProperties);
        }
        if ($this->postMappingMethod !== null && $rc->hasMethod($this->postMappingMethod)) {
            $rc->getMethod($this->postMappingMethod)->invoke($object, ...$this->postMappingMethodArguments);
        }
        return $object;
    }

    /**
     * 一次遍历完成直接键选择；遇到兼容变体时把已选直接键并入 selected，
     * 后续键继续使用完整解析，避免二次扫描输入。
     *
     * @return array{bool, array<int, string>, array<int, array{string, mixed}>, array}
     */
    private function selectFields(array $input, array $plan): array
    {
        $selected = [];
        $unknown = [];
        $directKeys = [];
        $directInput = true;
        foreach ($input as $key => $value) {
            $rawKey = (string) $key;
            if ($directInput) {
                $index = $plan['directMap'][$key] ?? null;
                if ($index !== null) {
                    if (isset($directKeys[$index])) {
                        $directKeys[$index] = $this->selectPreferredKey(
                            $plan['fields'][$index],
                            $directKeys[$index],
                            $rawKey
                        );
                    } else {
                        $directKeys[$index] = $rawKey;
                    }
                    continue;
                }
                $directInput = false;
                foreach ($directKeys as $directIndex => $directKey) {
                    $selected[$directIndex] = [$directKey, $input[$directKey]];
                }
                $directKeys = [];
            }
            $index = $this->resolveFieldIndex($rawKey, $plan);
            if ($index === null) {
                $unknown[$key] = $value;
                continue;
            }
            if (isset($selected[$index]) && $selected[$index][0] !== $rawKey) {
                $winner = $this->selectPreferredKey($plan['fields'][$index], $selected[$index][0], $rawKey);
                if ($winner === $rawKey) {
                    $selected[$index] = [$rawKey, $value];
                }
                continue;
            }
            $selected[$index] = [$rawKey, $value];
        }
        return [$directInput, $directKeys, $selected, $unknown];
    }

    /**
     * 主键未传入时，按原输入顺序选择该字段的最终键。
     * 常规 canonical 输入不会进入此回退路径。
     */
    private function selectFieldKey(array $field, array $plan, array $input, int $fieldIndex): ?string
    {
        $selectedKey = null;
        foreach ($input as $key => $value) {
            $rawKey = (string) $key;
            $index = $plan['directMap'][$key] ?? null;
            if ($index === null) {
                $index = $this->resolveFieldIndex($rawKey, $plan);
            }
            if ($index !== $fieldIndex) {
                continue;
            }
            if ($selectedKey === null) {
                $selectedKey = $rawKey;
                continue;
            }
            $selectedKey = $this->selectPreferredKey($field, $selectedKey, $rawKey);
        }
        return $selectedKey;
    }

    /**
     * 将原始节点解析为验证/RPC 所需的 canonical 数组。
     * 只解析键和嵌套结构，不做对象写入；$mappedTypes 按引用收集各嵌套路径
     * 的实际类型。未知键不会被丢弃，避免改变自定义验证规则的输入语义。
     */
    private function resolveData(
        string $className,
        mixed $json,
        MappingContext $context,
        int $depth,
        string $path,
        array &$mappedTypes,
    ): array {
        $plan = $this->getMappingPlan($className);
        $input = $this->inputArray($json, $className);
        $context->enterNode(count($input), $className, $depth);
        [$directInput, $directKeys, $selected, $unknown] = $this->selectFields($input, $plan);

        $data = [];
        foreach ($plan['fields'] as $index => $field) {
            if ($directInput) {
                if (! array_key_exists($index, $directKeys)) {
                    continue;
                }
                $value = $input[$directKeys[$index]];
            } else {
                if (! isset($selected[$index])) {
                    continue;
                }
                [, $value] = $selected[$index];
            }
            $property = $field['property'];
            if ($property->className === null && $property->arrClassName === null
                && ($field['type'] === null || $field['simple'] || $field['flat'])
            ) {
                $data[$field['name']] = $value;
                continue;
            }
            $fieldPath = $this->appendPath($path, $field['name']);
            $data[$field['name']] = $this->resolveNestedValue(
                $field,
                $value,
                $context,
                $depth,
                $fieldPath,
                $mappedTypes
            );
        }
        foreach ($unknown as $key => $value) {
            $data[$key] = $value;
        }
        return $data;
    }

    private function resolveNestedValue(
        array $field,
        mixed $value,
        MappingContext $context,
        int $depth,
        string $path,
        array &$mappedTypes,
    ): mixed {
        $property = $field['property'];
        $className = $property->className;
        $arrClassName = $property->arrClassName;
        $arrayDepth = max(1, $property->arrayDepth);

        // PropertyManager 未识别的类型（接口、bIgnoreVisibility 私有字段等）
        // 回退到映射计划的字段类型，与 writeField 的类型选择保持一致。
        if ($className === null && $arrClassName === null) {
            $planType = $field['type'];
            if ($planType !== null && ! $field['simple'] && ! $field['flat']) {
                $arrayDepth = 0;
                $elementType = $planType;
                while ($this->isArrayOfType($elementType)) {
                    ++$arrayDepth;
                    $elementType = substr($elementType, 0, -2);
                }
                if ($arrayDepth > 0) {
                    $arrClassName = $elementType;
                } elseif ($this->isNestableClass($elementType)) {
                    $className = $elementType;
                }
            }
        }

        if ($className !== null) {
            if ((! is_array($value) && ! is_object($value)) || $value instanceof $className) {
                return $value;
            }
            $resolvedClass = $this->resolveMappedClass($className, $value);
            if ($resolvedClass === null || $value instanceof $resolvedClass) {
                return $value;
            }
            $mappedTypes[$path] = $resolvedClass;
            return $this->resolveData(
                $resolvedClass,
                $value,
                $context,
                $depth + 1,
                $path,
                $mappedTypes
            );
        }

        if ($arrClassName === null || (! is_array($value) && ! is_object($value))) {
            return $value;
        }

        return $this->resolveClassArray(
            $arrClassName,
            max(1, $arrayDepth),
            $value,
            $context,
            $depth,
            $path,
            $mappedTypes
        );
    }

    /**
     * 递归解析 Child[]、Child[][] 等数组，直到叶子 DTO。
     * 中间数组只保留结构；叶节点才执行 classMap/classFactories 和字段解析。
     * null 元素保留，由 strict-null 配置和验证递归决定后续行为，不在这里强制转换。
     */
    private function resolveClassArray(
        string $className,
        int $remainingDepth,
        mixed $value,
        MappingContext $context,
        int $depth,
        string $path,
        array &$mappedTypes,
    ): array {
        $result = [];
        foreach ($value as $key => $item) {
            $itemPath = $this->appendPath($path, $key);
            if ($remainingDepth > 1) {
                $result[$key] = is_array($item) || is_object($item)
                    ? $this->resolveClassArray(
                        $className,
                        $remainingDepth - 1,
                        $item,
                        $context,
                        $depth,
                        $itemPath,
                        $mappedTypes
                    )
                    : $item;
                continue;
            }

            if ($item === null || (! is_array($item) && ! is_object($item))) {
                $result[$key] = $item;
                continue;
            }
            $itemClass = $this->resolveMappedClass($className, $item);
            if ($itemClass === null || $item instanceof $itemClass) {
                $result[$key] = $item;
                continue;
            }
            $mappedTypes[$itemPath] = $itemClass;
            $result[$key] = $this->resolveData(
                $itemClass,
                $item,
                $context,
                $depth + 1,
                $itemPath,
                $mappedTypes
            );
        }
        return $result;
    }

    /**
     * 应用 classMap/classFactories 找到实际待解析的类。
     * classFactories 需要实例化一次以确定具体类，与 createInstance 的选择顺序一致。
     */
    private function resolveMappedClass(string $className, mixed $value): ?string
    {
        $className = ltrim($className, '\\');
        if ($this->classMap !== []) {
            $registeredType = isset($this->classMap[$className]) ? $className : '\\' . $className;
            $mapped = $this->getMappedType($registeredType, $value);
            if ($mapped === null) {
                return null;
            }
            $className = ltrim($mapped, '\\');
        }
        $factory = $this->classFactories[$className] ?? $this->classFactories['\\' . $className] ?? null;
        if ($factory !== null) {
            $instance = is_array($value) || is_object($value) ? $factory() : $factory($value);
            $className = get_class($instance);
        }
        return class_exists($className) ? $className : null;
    }

    /**
     * 计划类型是否可能指向可实例化的类（含 classMap/classFactories 目标）。
     */
    private function isNestableClass(string $type): bool
    {
        $type = ltrim($type, '\\');
        return class_exists($type)
            || isset($this->classMap[$type])
            || isset($this->classMap['\\' . $type])
            || isset($this->classFactories[$type])
            || isset($this->classFactories['\\' . $type]);
    }

    /**
     * 转换并写入单个字段。
     * accessor 为 null 表示不可写（如 readonly、static 或无可见访问器），
     * 与未知字段区分处理。简单类型判断在计划中预计算；嵌套对象/数组继续
     * 使用当前请求的 context、resolved 标记和 mappedTypes。
     */
    private function writeField(
        object $object,
        array $plan,
        int $index,
        mixed $jvalue,
        MappingContext $context,
        int $depth,
        string $path,
        bool $resolved,
        array $mappedTypes = [],
    ): void {
        $field = $plan['fields'][$index];
        $accessor = $field['accessor'];
        if ($accessor === null) {
            if ($this->bExceptionOnUndefinedProperty) {
                throw new \JsonMapper_Exception(
                    'JSON property "' . $field['name'] . '" has no public setter method'
                    . ' in object of type ' . $plan['className']
                );
            }
            $this->log(
                'info',
                'Property {property} has no public setter method in {class}',
                ['property' => $field['name'], 'class' => $plan['className']]
            );
            return;
        }
        $type = $field['type'];
        $key = $field['name'];
        $className = $plan['className'];

        if ($field['nullable'] || ! $this->bStrictNullTypes) {
            if ($jvalue === null) {
                $this->setProperty($object, $accessor, null);
                return;
            }
        } elseif ($jvalue === null) {
            throw new \JsonMapper_Exception(
                'JSON property "' . $key . '" in class "' . $className . '" must not be NULL'
            );
        }

        $fieldPath = null;
        if ($resolved && ! $field['simple'] && $mappedTypes !== []) {
            $fieldPath = $this->appendPath($path, $key);
            if (isset($mappedTypes[$fieldPath])) {
                // Valid 路径复用解析阶段选定的 classMap/classFactory 结果，避免二次调用工厂。
                $type = $mappedTypes[$fieldPath];
            } elseif ($this->classMap !== []) {
                $type = $this->getMappedType($type, $jvalue);
            }
        } elseif ($this->classMap !== []) {
            $type = $this->getMappedType($type, $jvalue);
        }
        if ($type === null || $type === 'mixed') {
            $this->setProperty($object, $accessor, $jvalue);
            return;
        }
        $hasClassConfig = $this->classMap !== [] || $this->classFactories !== [];
        $isSimple = $hasClassConfig ? $this->isSimpleType($type) : $field['simple'];
        $isFlat = $hasClassConfig ? $this->isFlatType($type) : $field['flat'];
        if (! $isSimple && $this->isObjectOfSameType($type, $jvalue)) {
            $this->setProperty($object, $accessor, $jvalue);
            return;
        }
        if ($isSimple && ! (is_array($jvalue) && $field['variadic'])) {
            if ($isFlat && ! $this->isFlatType(gettype($jvalue))) {
                throw new \JsonMapper_Exception(
                    'JSON property "' . $key . '" in class "' . $className . '" is of type '
                    . gettype($jvalue) . ' and cannot be converted to ' . $type
                );
            }
            settype($jvalue, $type);
            $this->setProperty($object, $accessor, $jvalue);
            return;
        }
        if ($type === '') {
            throw new \JsonMapper_Exception('Empty type at property "' . $className . '::$' . $key . '"');
        }
        $fieldPath ??= $this->appendPath($path, $key);
        if (strpos(str_replace('|null', '', $type), '|')) {
            throw new \JsonMapper_Exception('Cannot decide which of the union types shall be used: ' . $type);
        }

        if ($field['variadic'] && ! is_array($jvalue) && $this->isFlatType(gettype($jvalue))) {
            // 上游 variadic setter 接受单个标量作为唯一实参。
            $jvalue = [$jvalue];
        }

        $array = null;
        $subtype = null;
        if ($this->isArrayOfType($type)) {
            $array = [];
            $subtype = substr($type, 0, -2);
        } elseif (substr($type, -1) === ']') {
            [$proptype, $subtype] = explode('[', substr($type, 0, -1));
            $array = $proptype === 'array' ? [] : $this->createInstance($proptype, false, $jvalue);
        } elseif (is_array($jvalue) && $this->hasVariadicArrayType($accessor)) {
            $array = [];
            $subtype = $type;
        } elseif (is_a($type, 'ArrayAccess', true) && is_a($type, 'Traversable', true)) {
            $array = $this->createInstance($type, false, $jvalue);
        }

        if ($array !== null) {
            if (! is_array($jvalue) && $this->isFlatType(gettype($jvalue))) {
                throw new \JsonMapper_Exception(
                    'JSON property "' . $key . '" must be an array, ' . gettype($jvalue) . ' given'
                );
            }
            $subtypeNullable = $this->isNullable($subtype);
            $subtype = $this->getFullNamespace($this->removeNullable($subtype), $plan['namespace']);
            if ($subtypeNullable) {
                $subtype = '?' . $subtype;
            }
            $child = $this->mapArrayInternal(
                $jvalue,
                $array,
                $subtype,
                $key,
                $context,
                $depth,
                $fieldPath,
                $resolved,
                $mappedTypes
            );
        } elseif ($this->isFlatType(gettype($jvalue))) {
            if ($this->bStrictObjectTypeChecking
                && ! is_subclass_of($type, \BackedEnum::class)
                && ! $this->getClassFactory($type)
            ) {
                throw new \JsonMapper_Exception(
                    'JSON property "' . $key . '" must be an object, ' . gettype($jvalue) . ' given'
                );
            }
            $child = $this->createInstance($type, true, $jvalue);
        } else {
            $child = $this->createInstance($type, false, $jvalue);
            $this->mapNode($jvalue, $child, $context, $depth + 1, $fieldPath, $resolved, $mappedTypes);
        }
        $this->setProperty($object, $accessor, $child);
    }

    /**
     * 属性写入边界统一保留 JsonMapper 异常族，避免联合类型和 variadic null 泄漏 TypeError。
     */
    protected function setProperty(object $object, ReflectionMethod|ReflectionProperty $accessor, mixed $value): void
    {
        try {
            parent::setProperty($object, $accessor, $value);
        } catch (\TypeError $exception) {
            throw new \JsonMapper_Exception($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * 保留上游 JsonMapper 的数组转换、null、class factory 和 ArrayObject 行为。
     * 与公共 map 分离是为了透传请求预算和 resolved 类型信息，避免嵌套数组
     * 重置上下文或重复解析输入。
     */
    private function mapArrayInternal(
        array|object $json,
        mixed $array,
        ?string $class,
        string $parentKey,
        MappingContext $context,
        int $depth,
        string $path,
        bool $resolved,
        array $mappedTypes = [],
    ): mixed {
        $isNullable = $this->isNullable($class);
        $class = $this->removeNullable($class);
        $originalClass = $class;
        foreach ($json as $key => $jvalue) {
            if ($jvalue === null && ! $isNullable && $this->bStrictNullTypesInArrays) {
                throw new \JsonMapper_Exception(
                    'JSON property "' . ($parentKey !== '' ? $parentKey : '?') . '[' . $key . ']" must not be NULL'
                );
            }

            $itemPath = $this->appendPath($path, $key);
            $class = $resolved && isset($mappedTypes[$itemPath])
                ? $mappedTypes[$itemPath]
                : ($this->classMap === [] ? $originalClass : $this->getMappedType($originalClass, $jvalue));
            if ($class === null) {
                $array[$key] = $jvalue;
            } elseif ($this->isArrayOfType($class)) {
                $array[$key] = $this->mapArrayInternal(
                    $jvalue,
                    [],
                    substr($class, 0, -2),
                    $parentKey,
                    $context,
                    $depth,
                    $itemPath,
                    $resolved,
                    $mappedTypes
                );
            } elseif ($this->isFlatType(gettype($jvalue))) {
                if ($jvalue === null) {
                    $array[$key] = null;
                } elseif ($this->isSimpleType($class)) {
                    settype($jvalue, $class);
                    $array[$key] = $jvalue;
                } elseif ($this->bStrictObjectTypeChecking
                    && ! is_subclass_of($class, \BackedEnum::class)
                    && ! $this->getClassFactory($class)
                ) {
                    throw new \JsonMapper_Exception(
                        'JSON property "' . ($parentKey !== '' ? $parentKey : '?') . '[' . $key . ']"'
                        . ' must be an object, ' . gettype($jvalue) . ' given'
                    );
                } else {
                    $array[$key] = $this->createInstance($class, true, $jvalue);
                }
            } elseif ($this->isFlatType($class)) {
                throw new \JsonMapper_Exception(
                    'JSON property "' . ($parentKey !== '' ? $parentKey : '?') . '" is an array of type "'
                    . $class . '" but contained a value of type "' . gettype($jvalue) . '"'
                );
            } elseif (is_a($class, 'ArrayObject', true)) {
                $array[$key] = $this->mapArrayInternal(
                    $jvalue,
                    $this->createInstance($class),
                    null,
                    $parentKey,
                    $context,
                    $depth,
                    $path,
                        $resolved,
                    $mappedTypes
                );
            } else {
                $child = $this->createInstance($class, false, $jvalue);
                $array[$key] = $this->mapNode(
                    $jvalue,
                    $child,
                    $context,
                    $depth + 1,
                    $itemPath,
                    $resolved,
                    $mappedTypes
                );
            }
        }
        return $array;
    }

    /**
     * 处理需要异常或回调的未消费输入键。
     * handler 和异常消息使用上游 JsonMapper 的 safe key；handler 映射到已知
     * 字段后复用正常写入流程。普通映射忽略未知键，不记录日志或缓存原始键。
     */
    private function handleUnknownProperties(
        object $object,
        array $plan,
        array $unknown,
        array &$providedProperties,
        MappingContext $context,
        int $depth,
        string $path,
    ): void {
        foreach ($unknown as $key => $value) {
            $safeKey = $this->getSafeName((string) $key);
            if ($this->bExceptionOnUndefinedProperty) {
                throw new \JsonMapper_Exception(
                    'JSON property "' . $safeKey . '" does not exist in object of type ' . $plan['className']
                );
            }
            if ($this->undefinedPropertyHandler !== null) {
                $undefinedPropertyKey = call_user_func($this->undefinedPropertyHandler, $object, $safeKey, $value);
                if (is_string($undefinedPropertyKey)) {
                    $index = $this->resolveFieldIndex($undefinedPropertyKey, $plan);
                    if ($index !== null) {
                        $providedProperties[$plan['fields'][$index]['name']] = true;
                        $this->writeField(
                            $object,
                            $plan,
                            $index,
                            $value,
                            $context,
                            $depth,
                            $path,
                                        false
                        );
                    }
                }
                continue;
            }
        }
    }

    /**
     * @internal 按类缓存的唯一输入映射计划，测试可用子类统计构建次数。
     * visibility 属于访问器决策的一部分，运行中切换时必须使旧计划失效。
     */
    protected function getMappingPlan(string $className): array
    {
        $className = ltrim($className, '\\');
        if ($this->mappingPlanVisibility !== $this->bIgnoreVisibility) {
            $this->mappingPlans = [];
            $this->mappingPlanVisibility = $this->bIgnoreVisibility;
        }
        if (isset($this->mappingPlans[$className])) {
            return $this->mappingPlans[$className];
        }
        return $this->mappingPlans[$className] = $this->buildMappingPlan($className);
    }

    /**
     * 构建一次类级映射计划，热路径只读，不保存请求数据。
     *
     * 关键结构：
     * - fields：字段索引 => 主键、属性、访问器、类型、可空性和简单类型标记。
     * - keyMap：可直接匹配的 PHP 属性名/JSONField 别名 => 字段索引。
     * - variantMap：预计算的常见兼容变体（snake/kebab/Pascal/setter 形式）=> 字段索引。
     * - directMap：keyMap + variantMap，供回退解析使用。
     * - safeMap：连字符等安全键形式 => 字段索引。
     * - setterMap：合法 setter 名（含 setID 等缩写）=> 字段索引。
     * - insensitiveMap：仅供规范化键使用的受限大小写回退表。
     * - propertyKeys：真实 PHP 属性名集合，供 ResolvedInput 使用。
     * - namespace/className/reflection：类型解析和 postMapping 所需类信息。
     *
     * 各表共用同一个字段索引，避免输入解析与最终写入访问器错配。
     */
    protected function buildMappingPlan(string $className): array
    {
        $className = ltrim($className, '\\');
        $rc = ReflectionManager::reflectClass($className);
        [$methods, $reflectionProperties] = $this->getClassMetadata($rc);
        $methodsBySuffix = [];
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (! str_starts_with($methodName, 'set') || strlen($methodName) === 3
                || (! $method->isPublic() && ! $this->bIgnoreVisibility)
            ) {
                continue;
            }
            $methodsBySuffix[strtolower(substr($methodName, 3))][] = $method;
        }

        $fields = [];
        $keyMap = [];
        $setterMap = [];
        $setterSuffixes = [];
        $insensitiveMap = [];
        $propertyKeys = [];
        $propertyManager = $this->getPropertyManager();
        $properties = $propertyManager->getPropertyByClass($className);
        $declaredFields = [];
        $normalizedDeclaredFields = [];
        foreach ($properties as $fieldName => $property) {
            $declaredFields[strtolower($fieldName)] = true;
            // user_name + setUserName：同一属性的两种命名形式，不能登记为 setter-only 字段。
            $normalizedDeclaredFields[strtolower(str_replace('_', '', $fieldName))] = true;
        }
        foreach ($methodsBySuffix as $methodsForField) {
            // JsonMapper 也支持没有同名属性的 setter-only 字段，但不能与声明属性重复登记。
            foreach ($methodsForField as $method) {
                $fieldName = lcfirst(substr($method->getName(), 3));
                if (isset($declaredFields[strtolower($fieldName)])
                    || isset($normalizedDeclaredFields[strtolower(str_replace('_', '', $fieldName))])
                ) {
                    continue;
                }
                [$hasProperty, $accessor, $type] = $this->inspectProperty($rc, $fieldName);
                if ($hasProperty && $accessor instanceof ReflectionMethod) {
                    $properties[$fieldName] = $propertyManager->createPropertyFromType($type, $rc->getNamespaceName());
                    break;
                }
            }
        }
        foreach ($properties as $fieldName => $property) {
            $index = count($fields);
            [, $accessor, $type, $nullable] = $this->inspectProperty($rc, $fieldName);
            $type = $this->getFullNamespace($this->removeNullable($type), $rc->getNamespaceName());
            // 属性数组元数据优先；宽泛 setter 的嵌套类型回退到属性类声明。
            if ($property->arrClassName !== null) {
                $type = '\\' . $property->arrClassName . str_repeat('[]', max(1, $property->arrayDepth));
            } elseif ($property->arrSimpleType !== null) {
                $type = $property->arrSimpleType . str_repeat('[]', max(1, $property->arrayDepth));
            } elseif (in_array($type, ['mixed', 'object', 'iterable'], true) && $property->className !== null) {
                $type = '\\' . $property->className;
            }
            $fields[$index] = [
                'name' => $fieldName,
                'primaryKey' => $fieldName,
                'property' => $property,
                'accessor' => $accessor,
                'type' => $type,
                'nullable' => $nullable,
                'simple' => $type !== null && $this->isSimpleType($type),
                'flat' => $type !== null && $this->isFlatType($type),
                'variadic' => $accessor !== null && $this->hasVariadicArrayType($accessor),
            ];
            $propertyKeys[$fieldName] = true;
            $this->registerDirectKey($keyMap, $fields, $fieldName, $index, $className);

            $alias = $property->alias;
            if ($alias !== null && $alias !== $fieldName) {
                if ($alias === '') {
                    throw new DtoException("JSONField alias cannot be empty for {$className}::{$fieldName}");
                }
                $safeAlias = $this->getSafeName($alias);
                if ($safeAlias !== $alias) {
                    throw new DtoException(
                        "JSONField alias [{$alias}] for class {$className}::{$fieldName} is ambiguous with safe key "
                        . "[{$safeAlias}]; use an alias without hyphens"
                    );
                }
                $fields[$index]['primaryKey'] = $alias;
                $this->registerDirectKey($keyMap, $fields, $alias, $index, $className);
            }

            $setterMethods = $methodsBySuffix[strtolower($fieldName)] ?? [];
            if ($setterMethods === [] && str_contains($fieldName, '_')) {
                $normalizedSuffix = strtolower(str_replace('_', '', $fieldName));
                // 仅当没有同名 camelCase 属性时，才把 setUserName 归给 user_name。
                if (! isset($declaredFields[$normalizedSuffix])) {
                    $setterMethods = $methodsBySuffix[$normalizedSuffix] ?? [];
                }
            }
            foreach ($setterMethods as $method) {
                $methodName = $method->getName();
                if ($this->getSetterName(substr($methodName, 3)) !== $methodName) {
                    continue;
                }
                if (isset($setterMap[$methodName]) && $setterMap[$methodName] !== $index) {
                    throw new DtoException("Input setter {$methodName} for class {$className} is ambiguous");
                }
                $setterMap[$methodName] = $index;
                $setterSuffixes[$index] = substr($methodName, 3);
                break;
            }

            $reflectionProperty = $reflectionProperties[$fieldName] ?? null;
            if ($reflectionProperty !== null && ($reflectionProperty->isPublic() || $this->bIgnoreVisibility)) {
                $insensitiveMap[strtolower($fieldName)] ??= $index;
            }
        }

        $safeMap = [];
        foreach ($keyMap as $key => $index) {
            $safeKey = $this->getSafeName((string) $key);
            if (isset($safeMap[$safeKey]) && $safeMap[$safeKey] !== $index) {
                throw new DtoException("Input key [{$safeKey}] for class {$className} is ambiguous");
            }
            $safeMap[$safeKey] = $index;
        }

        // 预计算常见兼容变体，避免热路径反复做 camelCase/setter 名推导。
        // 候选项必须通过 getCamelCaseName 验证，保证与既有解析结果一致。
        $variantMap = [];
        foreach ($fields as $index => $field) {
            $fieldName = $field['name'];

            $kebab = strtolower((string) preg_replace('/(?<!^)([A-Z])/', '-$1', $fieldName));
            if ($kebab !== $fieldName && strtolower($this->getCamelCaseName($kebab)) === strtolower($fieldName)) {
                $this->addVariantKey($variantMap, $keyMap, $safeMap, $setterMap, $insensitiveMap, $kebab, $index);
            }

            $setterSuffix = $setterSuffixes[$index] ?? null;
            if ($setterSuffix !== null) {
                $snake = strtolower((string) preg_replace('/(?<!^)([A-Z])/', '_$1', $setterSuffix));
                foreach ([lcfirst($setterSuffix), $setterSuffix, $snake, str_replace('_', '-', $snake)] as $candidate) {
                    if ($candidate === '' || $candidate === $fieldName) {
                        continue;
                    }
                    if ($this->getCamelCaseName($candidate) !== $setterSuffix) {
                        continue;
                    }
                    $this->addVariantKey($variantMap, $keyMap, $safeMap, $setterMap, $insensitiveMap, $candidate, $index);
                }
            }
        }
        $directMap = $keyMap;
        foreach ($variantMap as $key => $index) {
            $directMap[$key] = $index;
        }

        return [
            'fields' => $fields,
            'keyMap' => $keyMap,
            'variantMap' => $variantMap,
            'directMap' => $directMap,
            'safeMap' => $safeMap,
            'setterMap' => $setterMap,
            'insensitiveMap' => $insensitiveMap,
            'propertyKeys' => $propertyKeys,
            'namespace' => $rc->getNamespaceName(),
            'className' => $className,
            'reflection' => $rc,
        ];
    }

    /**
     * 注册无需额外转换即可命中的唯一键；任何跨字段冲突都在构建计划时失败。
     */
    private function registerDirectKey(
        array &$keyMap,
        array $fields,
        string $key,
        int $index,
        string $className,
    ): void {
        if (isset($keyMap[$key]) && $keyMap[$key] !== $index) {
            $existingField = $fields[$keyMap[$key]]['name'];
            $fieldName = $fields[$index]['name'];
            throw new DtoException(
                "Input key [{$key}] for class {$className} is ambiguous between fields "
                . "[{$existingField}] and [{$fieldName}]; each field must have a unique property or alias key"
            );
        }
        $keyMap[$key] = $index;
    }

    /**
     * 登记预计算的兼容变体；仅缓存既有解析逻辑本身会映射到同一字段的键。
     */
    private function addVariantKey(
        array &$variantMap,
        array $keyMap,
        array $safeMap,
        array $setterMap,
        array $insensitiveMap,
        string $key,
        int $index,
    ): void {
        if (isset($keyMap[$key])) {
            return;
        }
        $safeKey = strpos($key, '-') !== false ? $this->getCamelCaseName($key) : $key;
        $resolved = $safeMap[$safeKey] ?? null;
        if ($resolved === null && $setterMap !== []) {
            $resolved = $setterMap[$this->getSetterName($safeKey)] ?? null;
        }
        if ($resolved === null && $safeKey !== $key) {
            $resolved = $insensitiveMap[strtolower($safeKey)] ?? null;
        }
        if ($resolved !== $index) {
            return;
        }
        if (isset($variantMap[$key]) && $variantMap[$key] !== $index) {
            return;
        }
        $variantMap[$key] = $index;
    }

    /**
     * 按兼容优先级解析字段：精确键 → 预计算变体 → 安全键 → setter → 受限大小写回退。
     * 不在这里做任意大小写猜测；未命中即交给未知字段策略。
     */
    private function resolveFieldIndex(string $rawKey, array $plan): ?int
    {
        $index = $plan['keyMap'][$rawKey] ?? null;
        if ($index !== null) {
            return $index;
        }
        $index = $plan['variantMap'][$rawKey] ?? null;
        if ($index !== null) {
            return $index;
        }
        $safeKey = $rawKey;
        if (strpos($rawKey, '-') !== false) {
            $safeKey = $this->getCamelCaseName($rawKey);
        }
        $index = $plan['safeMap'][$safeKey] ?? null;
        if ($index !== null) {
            return $index;
        }
        if ($plan['setterMap'] !== []) {
            $index = $plan['setterMap'][$this->getSetterName($safeKey)] ?? null;
            if ($index !== null) {
                return $index;
            }
        }
        if ($safeKey !== $rawKey) {
            return $plan['insensitiveMap'][strtolower($safeKey)] ?? null;
        }
        return null;
    }

    /**
     * 同一字段命中多个候选键时的优先级：
     * 显式外部别名 > PHP 属性名 > 兼容变体。
     * 同一优先级的键保留先出现的值，不再拒绝重复等价键。
     */
    private function selectPreferredKey(array $field, string $currentKey, string $newKey): string
    {
        return $this->keyPriority($field, $newKey) < $this->keyPriority($field, $currentKey)
            ? $newKey
            : $currentKey;
    }

    /**
     * 键优先级：别名 0，PHP 属性名 1，setter/大小写等兼容变体 2。
     */
    private function keyPriority(array $field, string $key): int
    {
        $alias = $field['property']->alias;
        if ($alias !== null && $alias !== $field['name']
            && ($key === $alias || $key === $this->getSafeName($alias))
        ) {
            return 0;
        }
        if ($key === $field['name']) {
            return 1;
        }
        return 2;
    }

    /**
     * 将输入节点转为按键读取的数组。
     * Traversable 必须走迭代器，不能使用 get_object_vars()；普通对象则只读取
     * 当前作用域可见的 public 属性，与直接 foreach 对象的输入语义一致。
     */
    private function inputArray(mixed $json, string $className): array
    {
        if ($json === null) {
            return [];
        }
        if (is_array($json)) {
            return $json;
        }
        if ($json instanceof \Traversable) {
            return iterator_to_array($json);
        }
        if (is_object($json)) {
            return get_object_vars($json);
        }
        throw new DtoException("Class: {$className} - data must be object or array");
    }

    private function appendPath(string $path, string|int $segment): string
    {
        return $path === '' ? (string) $segment : $path . '.' . $segment;
    }

    private function getPropertyManager(): PropertyManager
    {
        return $this->propertyManager ??= new PropertyManager(new DtoCommon(), new PropertyEnum());
    }

    public function getSetterName(string $name): string
    {
        return 'set' . $this->getCamelCaseName($this->getSafeName($name));
    }

    /**
     * variadic setter（setChildren(Child ...$children)）接收元素列表，
     * 元数据按元素类型 + [] 表示，与普通类数组保持一致。
     */
    private function variadicElementType(ReflectionMethod $method, ?string $type): ?string
    {
        if ($type === null || $type === '' || ! $this->hasVariadicArrayType($method) || $this->isArrayOfType($type)) {
            return $type;
        }
        return $type . '[]';
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->log('debug', $message, $context);
        }
    }

    /**
     * 缓存 inspectProperty 所需的反射信息。
     * 属性沿父链收集，确保父类 private 属性在 bIgnoreVisibility=true 时仍可
     * 按配置写入。
     */
    private function getClassMetadata(ReflectionClass $rc): array
    {
        $className = $rc->getName();
        if (isset($this->classMetadata[$className])) {
            return $this->classMetadata[$className];
        }

        $methods = [];
        foreach ($rc->getMethods() as $method) {
            $methods[strtolower($method->getName())] = $method;
        }

        $properties = [];
        $class = $rc;
        do {
            foreach ($class->getProperties() as $property) {
                if (! isset($properties[$property->getName()])) {
                    $properties[$property->getName()] = $property;
                }
            }
        } while ($class = $class->getParentClass());

        $this->classMetadata[$className] = [$methods, $properties];
        return $this->classMetadata[$className];
    }

    /**
     * Try to find out if a property exists in a given class.
     * Checks property first, falls back to setter method.
     *
     * @param ReflectionClass $rc Reflection class to check
     * @param string $name Property name
     *
     * @return array First value: if the property exists
     *               Second value: the accessor to use (
     *               ReflectionMethod or ReflectionProperty, or null)
     *               Third value: type of the property
     *               Fourth value: if the property is nullable
     */
    protected function inspectProperty(ReflectionClass $rc, string $name): array
    {
        [$methods, $properties] = $this->getClassMetadata($rc);
        $rprop = $properties[$name] ?? null;
        $class = $rprop?->getDeclaringClass() ?? $rc;

        $setter = 'set' . $this->getCamelCaseName($name);
        $rmeth = $methods[strtolower($setter)] ?? null;

        if ($rmeth !== null) {
            // 任意大小写变体仍需精确命中；声明属性键可使用 setID 等缩写 setter。
            $isDeclaredPropertySetter = $rprop !== null && strcasecmp($rmeth->getName(), $setter) === 0;
            if (($rmeth->getName() === $setter || $isDeclaredPropertySetter)
                && ($rmeth->isPublic() || $this->bIgnoreVisibility)
            ) {
                $isNullable = false;
                $rparams = $rmeth->getParameters();
                if (count($rparams) > 0) {
                    $isNullable = $rparams[0]->allowsNull();
                    $ptype      = $rparams[0]->getType();
                    if ($ptype !== null) {
                        $typeName = $this->stringifyReflectionType($ptype);
                        //allow overriding an "array" type hint
                        // with a more specific class in the docblock
                        if ($typeName !== 'array' && ! str_contains($typeName, '|')) {
                            return array(
                                true, $rmeth,
                                $this->variadicElementType($rmeth, $typeName),
                                $isNullable,
                            );
                        }
                    }
                }

                $docblock    = $rmeth->getDocComment();
                $annotations = static::parseAnnotations((string) $docblock);

                if (!isset($annotations['param'][0])) {
                    return array(true, $rmeth, null, $isNullable);
                }
                list($type) = explode(' ', trim($annotations['param'][0]));
                if (str_contains($type, '|')) {
                    return array(true, $rmeth, null, $isNullable);
                }
                return array(true, $rmeth, $this->variadicElementType($rmeth, $type), $this->isNullable($type));
            }
        }

        if ($rprop !== null) {
            if ($rprop->isStatic() || $rprop->isReadOnly()) {
                return array(true, null, null, false);
            }
            if ($rprop->isPublic() || $this->bIgnoreVisibility) {
                $docblock = $rprop->getDocComment();
                if ($docblock === false && $class->hasMethod('__construct')) {
                    $docblock = $class->getMethod('__construct')->getDocComment();
                }
                // 修改：优先读取 ArrayType 注解，并使用 DocBlockFactory 解析命名空间
                $annotations = $this->parseAnnotationsNew($rc, $rprop, $docblock);

                if (!isset($annotations['var'][0])) {
                    if ($rprop->hasType() && isset($annotations['param'])) {
                        foreach ($annotations['param'] as $param) {
                            if (strpos($param . ' ', '$' . $rprop->getName() . ' ') !== false
                                || strpos($param . "\t", '$' . $rprop->getName() . "\t") !== false
                            ) {
                                list($type) = explode(' ', $param);
                                return array(
                                    true, $rprop, $type, $this->isNullable($type)
                                );
                            }
                        }
                    }

                    // If there is no annotations (higher priority) inspect
                    // if there's a scalar type being defined
                    if ($rprop->hasType()) {
                        $rPropType = $rprop->getType();
                        $propTypeName = $this->stringifyReflectionType($rPropType);
                        if ($this->isSimpleType($propTypeName)) {
                            return array(
                                true,
                                $rprop,
                                $propTypeName,
                                $rPropType->allowsNull()
                            );
                        }
                        if (str_contains($propTypeName, '|')) {
                            // 联合类型无法确定映射目标，按原值写入。
                            return array(true, $rprop, null, $rPropType->allowsNull());
                        }

                        return array(
                            true,
                            $rprop,
                            '\\' . ltrim($propTypeName, '\\'),
                            $rPropType->allowsNull()
                        );
                    }

                    return array(true, $rprop, null, false);
                }

                //support "@var type description"
                list($type) = explode(' ', $annotations['var'][0]);

                return array(true, $rprop, $type, $this->isNullable($type));
            } else {
                //no setter, private property
                return array(true, null, null, false);
            }
        }

        //no setter, no property
        return array(false, null, null, false);
    }

    /**
     * 解析属性的类型注解，优先使用 ArrayType 注解，其次解析 @var 标签。
     * DocBlockFactory 在 Mapper 实例内复用，命名空间上下文按属性声明类缓存；继承属性
     * 不会因当前 DTO 所在命名空间不同而解析错类型。
     *
     * @param false|string $docblock 属性或构造方法的 docblock
     *
     * @return array Array of arrays.
     *               Key is the "@"-name like "param",
     *               each value is an array of the @-lines
     */
    public function parseAnnotationsNew(ReflectionClass $rc, ReflectionProperty $reflectionProperty, $docblock): array
    {
        $annotations = [];
        /** @var ReflectionAttribute $arrayType */
        $arrayType = $reflectionProperty->getAttributes(ArrayType::class)[0] ?? [];
        if (! empty($arrayType)) {
            $type = $arrayType->getArguments()[0] ?? $arrayType->getArguments()['value'] ?? null;
            if (! empty($type)) {
                if ($type instanceof PhpType) {
                    $type = $type->getValue();
                }
                $isSimpleType = $this->isSimpleType($type);
                if ($isSimpleType) {
                    $annotations['var'][] = $type . '[]';
                } else {
                    $annotations['var'][] = '\\' . trim($type, '\\') . '[]';
                }
                return $annotations;
            }
        }
        if (! is_string($docblock)) {
            return [];
        }
        $this->docBlockFactory ??= DocBlockFactory::createInstance();
        $this->contextFactory ??= new ContextFactory();
        $declaringClass = $reflectionProperty->getDeclaringClass();
        $declaringClassName = $declaringClass->getName();
        if (! isset($this->docBlockContexts[$declaringClassName])) {
            $fileName = $declaringClass->getFileName();
            $source = $fileName === false ? '' : (string) @file_get_contents($fileName);
            $this->docBlockContexts[$declaringClassName] = $this->contextFactory->createForNamespace(
                $declaringClass->getNamespaceName(),
                $source
            );
        }
        $context = $this->docBlockContexts[$declaringClassName];
        try {
            $block = $this->docBlockFactory->create($docblock, $context);
        } catch (\Throwable $exception) {
            // 第三方类可能包含无法解析的 docblock；忽略该注解而不是阻断映射。
            return [];
        }
        foreach ($block->getTags() as $tag) {
            if ($tag instanceof Var_) {
                $normalized = $this->normalizeDocblockType($tag->getType());
                if ($normalized !== null) {
                    $annotations[$tag->getName()][] = $normalized;
                }
            } elseif ($tag instanceof Param) {
                // 构造器 @param 是提升属性与无 @var 属性的类型回退来源，
                // 保留变量名供 inspectProperty 按属性名匹配。
                $normalized = $this->normalizeDocblockType($tag->getType());
                $variableName = $tag->getVariableName();
                if ($normalized !== null && $variableName !== null) {
                    $annotations[$tag->getName()][] = $normalized . ' $' . $variableName;
                }
            }
        }
        return $annotations;
    }

    /**
     * 将 docblock 类型归一为 JsonMapper 可消费的字符串。
     * 支持 nullable、仅含一个非 null 类型的联合类型及 list/array 泛型；
     * 多类型联合、可空数组元素和无法确定的泛型返回 null，由调用方回退到反射类型。
     */
    private function normalizeDocblockType(Type $type): ?string
    {
        if ($type instanceof Nullable) {
            $normalized = $this->normalizeDocblockType($type->getActualType());
            return $normalized === null ? null : '?' . ltrim($normalized, '?');
        }
        if ($type instanceof Compound) {
            $nullable = false;
            $nonNullTypes = [];
            foreach ($type as $part) {
                if ($part instanceof Nullable) {
                    $nullable = true;
                    $part = $part->getActualType();
                }
                if ($part instanceof Null_) {
                    $nullable = true;
                    continue;
                }
                $nonNullTypes[] = $part;
            }
            if (count($nonNullTypes) !== 1) {
                return null;
            }
            $normalized = $this->normalizeDocblockType($nonNullTypes[0]);
            if ($normalized === null) {
                return null;
            }
            return $nullable ? '?' . ltrim($normalized, '?') : $normalized;
        }
        if ($type instanceof AbstractList) {
            $valueType = $type->getValueType();
            if ($valueType instanceof Mixed_) {
                return 'array';
            }
            $normalized = $this->normalizeDocblockType($valueType);
            if ($normalized === null || str_starts_with($normalized, '?')) {
                return null;
            }
            return $normalized . '[]';
        }

        $normalized = (string) $type;
        if (str_contains($normalized, '<')) {
            return null;
        }
        return $normalized;
    }
}
