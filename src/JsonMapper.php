<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Di\ReflectionManager;
use Hyperf\DTO\Annotation\ArrayType;
use Hyperf\DTO\Exception\DtoException;
use Hyperf\DTO\Mapping\MappingContext;
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
 * 实例缓存只保存类元数据；请求级预算保存在 MappingContext 中，避免协程间串扰。
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
     * 禁止回退到上游通用映射，DTO 统一使用 simpleMap()。
     */
    public function map($json, $object)
    {
        throw new \BadMethodCallException('JsonMapper::map() is disabled; use simpleMap().');
    }

    /**
     * DTO 专用映射入口：每次公开调用创建独立预算，嵌套对象共享该预算。
     * 支持对象/数组输入以及类名字符串目标。
     */
    public function simpleMap($json, $object, ?MappingContext $context = null)
    {
        if ($this->bEnforceMapType && ! is_object($json)) {
            throw new \InvalidArgumentException(
                'JsonMapper::simpleMap() requires first argument to be an object'
                . ', ' . gettype($json) . ' given.'
            );
        }
        if (! is_object($object) && (! is_string($object) || ! class_exists($object))) {
            throw new \InvalidArgumentException(
                'JsonMapper::simpleMap() requires second argument to '
                . 'be an object or existing class name'
                . ', ' . gettype($object) . ' given.'
            );
        }
        if (is_string($object)) {
            $object = $this->createInstance($object);
        }
        return $this->mapNode($json, $object, $context ?? new MappingContext(), 0);
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
            0
        );
    }

    /**
     * 映射单个 DTO 节点，只按字段主键读取输入。
     */
    private function mapNode(
        mixed $json,
        object $object,
        MappingContext $context,
        int $depth,
    ): object {
        $className = get_class($object);
        $plan = $this->getMappingPlan($className);
        $input = $this->inputArray($json, $className);
        $context->enterNode(count($input), $className, $depth);

        foreach ($plan['fields'] as $index => $field) {
            $primaryKey = $field['primaryKey'];
            if (! array_key_exists($primaryKey, $input)) {
                continue;
            }
            $this->writeField($object, $plan, $index, $input[$primaryKey], $context, $depth);
        }

        return $object;
    }

    /**
     * 转换并写入单个字段。
     * accessor 为 null 表示不可写（如 readonly、static 或无可见访问器），
     * 与未知字段区分处理。简单类型判断在计划中预计算；嵌套对象/数组继续
     * 使用当前请求的 MappingContext。
     */
    private function writeField(
        object $object,
        array $plan,
        int $index,
        mixed $jvalue,
        MappingContext $context,
        int $depth,
    ): void {
        $field = $plan['fields'][$index];
        $accessor = $field['accessor'];
        if ($accessor === null) {
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

        if ($this->classMap !== []) {
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
                $depth
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
            $this->mapNode($jvalue, $child, $context, $depth + 1);
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
     * 与公共 map 分离是为了透传请求预算，避免嵌套数组重置上下文。
     */
    private function mapArrayInternal(
        array|object $json,
        mixed $array,
        ?string $class,
        string $parentKey,
        MappingContext $context,
        int $depth,
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

            $class = $this->classMap === [] ? $originalClass : $this->getMappedType($originalClass, $jvalue);
            if ($class === null) {
                $array[$key] = $jvalue;
            } elseif ($this->isArrayOfType($class)) {
                $array[$key] = $this->mapArrayInternal(
                    $jvalue,
                    [],
                    substr($class, 0, -2),
                    $parentKey,
                    $context,
                    $depth
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
                    $depth
                );
            } else {
                $child = $this->createInstance($class, false, $jvalue);
                $array[$key] = $this->mapNode(
                    $jvalue,
                    $child,
                    $context,
                    $depth + 1
                );
            }
        }
        return $array;
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
     * - namespace/className：嵌套类型解析和异常信息。
     *
     * 字段索引贯穿整个计划，避免输入解析与最终写入访问器错配。
     */
    protected function buildMappingPlan(string $className): array
    {
        $className = ltrim($className, '\\');
        $rc = ReflectionManager::reflectClass($className);
        [$methods] = $this->getClassMetadata($rc);
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
        }

        return [
            'fields' => $fields,
            'namespace' => $rc->getNamespaceName(),
            'className' => $className,
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
