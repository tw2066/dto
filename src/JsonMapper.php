<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\DTO\Annotation\ArrayType;
use Hyperf\DTO\Type\PhpType;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\Types\AbstractList;
use phpDocumentor\Reflection\Types\Compound;
use phpDocumentor\Reflection\Types\ContextFactory;
use phpDocumentor\Reflection\Types\Mixed_;
use phpDocumentor\Reflection\Types\Null_;
use phpDocumentor\Reflection\Types\Nullable;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

class JsonMapper extends \JsonMapper
{
    public function map($json, $object)
    {
        if ($this->bEnforceMapType && !is_object($json)) {
            throw new \InvalidArgumentException(
                'JsonMapper::map() requires first argument to be an object'
                . ', ' . gettype($json) . ' given.'
            );
        }
        if (!is_object($object) && (!is_string($object) || !class_exists($object))) {
            throw new \InvalidArgumentException(
                'JsonMapper::map() requires second argument to '
                . 'be an object or existing class name'
                . ', ' . gettype($object) . ' given.'
            );
        }

        if (is_string($object)) {
            $object = $this->createInstance($object);
        }

        $strClassName = get_class($object);
        $rc = new ReflectionClass($object);
        $strNs = $rc->getNamespaceName();
        $providedProperties = array();
        foreach ($json as $key => $jvalue) {
            $rawKey = (string) $key;
            $key = $this->getSafeName($rawKey);
            $allowPropertyCaseFallback = $key !== $rawKey;
            $providedProperties[$key] = true;

            // 使用原始键，避免连字符规范化键与原始大小写键共用错误结果。
            if (isset($this->arInspectedClasses[$strClassName][$rawKey])) {
                $inspection = $this->arInspectedClasses[$strClassName][$rawKey];
            } else {
                $inspection = $this->inspectProperty($rc, $key, $allowPropertyCaseFallback);
                if ($inspection[0]) {
                    $this->arInspectedClasses[$strClassName][$rawKey] = $inspection;
                }
            }

            list($hasProperty, $accessor, $type, $isNullable) = $inspection;

            if (!$hasProperty) {
                if ($this->bExceptionOnUndefinedProperty) {
                    throw new \JsonMapper_Exception(
                        'JSON property "' . $key . '" does not exist'
                        . ' in object of type ' . $strClassName
                    );
                } else if ($this->undefinedPropertyHandler !== null) {
                    $undefinedPropertyKey = call_user_func(
                        $this->undefinedPropertyHandler,
                        $object, $key, $jvalue
                    );

                    if (is_string($undefinedPropertyKey)) {
                        list($hasProperty, $accessor, $type, $isNullable)
                            = $this->inspectProperty($rc, $undefinedPropertyKey);
                    }
                } else {
                    $this->log(
                        'info',
                        'Property {property} does not exist in {class}',
                        array('property' => $key, 'class' => $strClassName)
                    );
                }

                if (!$hasProperty) {
                    continue;
                }
            }

            if ($accessor === null) {
                if ($this->bExceptionOnUndefinedProperty) {
                    throw new \JsonMapper_Exception(
                        'JSON property "' . $key . '" has no public setter method'
                        . ' in object of type ' . $strClassName
                    );
                }
                $this->log(
                    'info',
                    'Property {property} has no public setter method in {class}',
                    array('property' => $key, 'class' => $strClassName)
                );
                continue;
            }

            if ($isNullable || !$this->bStrictNullTypes) {
                if ($jvalue === null) {
                    $this->setProperty($object, $accessor, null);
                    continue;
                }
                $type = $this->removeNullable($type);
            } else if ($jvalue === null) {
                throw new \JsonMapper_Exception(
                    'JSON property "' . $key . '" in class "'
                    . $strClassName . '" must not be NULL'
                );
            }

            $type = $this->getFullNamespace($type, $strNs);
            $type = $this->getMappedType($type, $jvalue);

            if ($type === null || $type === 'mixed') {
                //no given type - simply set the json data
                $this->setProperty($object, $accessor, $jvalue);
                continue;
            } else if ($this->isObjectOfSameType($type, $jvalue)) {
                $this->setProperty($object, $accessor, $jvalue);
                continue;
            } else if ($this->isSimpleType($type)
                && !(is_array($jvalue) && $this->hasVariadicArrayType($accessor))
            ) {
                if ($this->isFlatType($type)
                    && !$this->isFlatType(gettype($jvalue))
                ) {
                    throw new \JsonMapper_Exception(
                        'JSON property "' . $key . '" in class "'
                        . $strClassName . '" is of type ' . gettype($jvalue) . ' and'
                        . ' cannot be converted to ' . $type
                    );
                }
                settype($jvalue, $type);
                $this->setProperty($object, $accessor, $jvalue);
                continue;
            }

            //FIXME: check if type exists, give detailed error message if not
            if ($type === '') {
                throw new \JsonMapper_Exception(
                    'Empty type at property "'
                    . $strClassName . '::$' . $key . '"'
                );

            } else if (strpos(str_replace('|null', '', $type), '|')) {
                throw new \JsonMapper_Exception(
                    'Cannot decide which of the union types shall be used: '
                    . $type
                );
            }

            $array = null;
            $subtype = null;
            if ($this->isArrayOfType($type)) {
                //array
                $array = array();
                $subtype = substr($type, 0, -2);
            } else if (substr($type, -1) == ']') {
                list($proptype, $subtype) = explode('[', substr($type, 0, -1));
                if ($proptype == 'array') {
                    $array = array();
                } else {
                    $array = $this->createInstance($proptype, false, $jvalue);
                }
            } else if (is_array($jvalue) && $this->hasVariadicArrayType($accessor)) {
                $array = array();
                $subtype = $type;
            } else {
                if (is_a($type, 'ArrayAccess', true)
                    && is_a($type, 'Traversable', true)
                ) {
                    $array = $this->createInstance($type, false, $jvalue);
                }
            }

            if ($array !== null) {
                if (!is_array($jvalue) && $this->isFlatType(gettype($jvalue))) {
                    throw new \JsonMapper_Exception(
                        'JSON property "' . $key . '" must be an array, '
                        . gettype($jvalue) . ' given'
                    );
                }

                $subtypeNullable = $this->isNullable($subtype);
                $cleanSubtype = $this->removeNullable($subtype);
                $subtype = $this->getFullNamespace($cleanSubtype, $strNs);
                if ($subtypeNullable) {
                    $subtype = '?' . $subtype;
                }
                $child = $this->mapArray($jvalue, $array, $subtype, $key);

            } else if ($this->isFlatType(gettype($jvalue))) {
                //use constructor parameter if we have a class
                // but only a flat type (i.e. string, int)
                if ($this->bStrictObjectTypeChecking
                    && !is_subclass_of($type, \BackedEnum::class)
                    && !$this->getClassFactory($type)
                ) {
                    throw new \JsonMapper_Exception(
                        'JSON property "' . $key . '" must be an object, '
                        . gettype($jvalue) . ' given'
                    );
                }
                $child = $this->createInstance($type, true, $jvalue);
            } else {
                $child = $this->createInstance($type, false, $jvalue);
                $this->map($jvalue, $child);
            }
            $this->setProperty($object, $accessor, $child);
        }

        if ($this->bExceptionOnMissingData) {
            $this->checkMissingData($providedProperties, $rc);
        }

        if ($this->bRemoveUndefinedAttributes) {
            $this->removeUndefinedAttributes($object, $providedProperties);
        }

        if ($this->postMappingMethod !== null
            && $rc->hasMethod($this->postMappingMethod)
        ) {
            $refDeserializePostMethod = $rc->getMethod(
                $this->postMappingMethod
            );
            $refDeserializePostMethod->invoke(
                $object, ...$this->postMappingMethodArguments
            );
        }

        return $object;
    }

    public function getSetterName(string $name): string
    {
        return 'set' . $this->getCamelCaseName($this->getSafeName($name));
    }

    /**
     * Log a message to the $logger object.
     *
     * @param string $level Logging level
     * @param string $message Text to log
     * @param array $context Additional information
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->log('debug', $message, $context);
        }
    }

    /**
     * Try to find out if a property exists in a given class.
     * Checks property first, falls back to setter method.
     *
     * @param ReflectionClass $rc Reflection class to check
     * @param string $name Property name
     * @param bool $allowPropertyCaseFallback Allow case-insensitive property matching for normalized keys
     *
     * @return array First value: if the property exists
     *               Second value: the accessor to use (
     *               ReflectionMethod or ReflectionProperty, or null)
     *               Third value: type of the property
     *               Fourth value: if the property is nullable
     */
    protected function inspectProperty(ReflectionClass $rc, string $name, bool $allowPropertyCaseFallback = false): array
    {
        // 先按声明名查找属性，用于区分合法缩写 setter 与任意大小写变体。
        $class = $rc;
        $rprop = null;
        do {
            if ($class->hasProperty($name)) {
                $rprop = $class->getProperty($name);
            }
        } while ($rprop === null && $class = $class->getParentClass());

        // 修改：别名 setter 方法优先
        $isSetDtoMethod = true;
        $setter = DtoConfig::getDtoAliasMethodName($name);
        if (! $rc->hasMethod($setter)) {
            $isSetDtoMethod = false;
            // try setter method first
            $setter = 'set' . $this->getCamelCaseName($name);
        }

        if ($rc->hasMethod($setter)) {
            $rmeth = $rc->getMethod($setter);
            // 任意大小写变体仍需精确命中；声明属性键可使用 setID 等缩写 setter。
            $isDeclaredPropertySetter = $rprop !== null && strcasecmp($rmeth->getName(), $setter) === 0;
            if (($rmeth->getName() === $setter || $isDeclaredPropertySetter)
                && ($rmeth->isPublic() || $this->bIgnoreVisibility || $isSetDtoMethod)
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
                        if ($typeName !== 'array') {
                            return array(
                                true, $rmeth,
                                $typeName,
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
                return array(true, $rmeth, $type, $this->isNullable($type));
            }
        }

        // 仅连字符规范化后的键允许属性大小写回退；原始大小写变体仍忽略。
        if ($rprop === null && $allowPropertyCaseFallback) {
            foreach ($rc->getProperties() as $property) {
                if (strcasecmp($property->getName(), $name) === 0) {
                    $rprop = $property;
                    $class = $property->getDeclaringClass();
                    break;
                }
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
     * 解析属性的类型注解，优先使用 ArrayType 注解，其次解析 @var 标签.
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
        $factory = DocBlockFactory::createInstance();
        $contextFactory = new ContextFactory();
        $declaringClass = $reflectionProperty->getDeclaringClass();
        $context = $contextFactory->createForNamespace(
            $declaringClass->getNamespaceName(),
            file_get_contents($declaringClass->getFileName())
        );
        $block = $factory->create($docblock, $context);
        foreach ($block->getTags() as $tag) {
            if ($tag instanceof Var_) {
                $annotations[$tag->getName()][] = $this->normalizeDocblockType($tag->getType());
            }
        }
        return $annotations;
    }

    private function normalizeDocblockType(Type $type): string
    {
        if ($type instanceof Nullable) {
            return '?' . ltrim($this->normalizeDocblockType($type->getActualType()), '?');
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
                throw new \JsonMapper_Exception('Unsupported docblock union type: ' . $type);
            }
            $normalized = $this->normalizeDocblockType($nonNullTypes[0]);
            return $nullable ? '?' . ltrim($normalized, '?') : $normalized;
        }
        if ($type instanceof AbstractList) {
            $valueType = $type->getValueType();
            if ($valueType instanceof Mixed_) {
                return 'array';
            }
            $normalized = $this->normalizeDocblockType($valueType);
            if (str_starts_with($normalized, '?')) {
                throw new \JsonMapper_Exception('Unsupported nullable array element type: ' . $type);
            }
            return $normalized . '[]';
        }

        $normalized = (string) $type;
        if (str_contains($normalized, '<')) {
            throw new \JsonMapper_Exception('Unsupported generic docblock type: ' . $normalized);
        }
        return $normalized;
    }
}
