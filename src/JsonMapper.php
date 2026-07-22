<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\DTO\Annotation\ArrayType;
use Hyperf\DTO\Type\PhpType;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Types\ContextFactory;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

class JsonMapper extends \JsonMapper
{
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
     *
     * @return array First value: if the property exists
     *               Second value: the accessor to use (
     *               ReflectionMethod or ReflectionProperty, or null)
     *               Third value: type of the property
     *               Fourth value: if the property is nullable
     */
    protected function inspectProperty(ReflectionClass $rc, string $name): array
    {
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
            // 修改：DTO 别名 setter 为私有方法，同样允许访问
            if ($rmeth->isPublic() || $this->bIgnoreVisibility || $isSetDtoMethod) {
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

        //now try to set the property directly
        //we have to look it up in the class hierarchy
        $class = $rc;
        $rprop = null;
        do {
            if ($class->hasProperty($name)) {
                $rprop = $class->getProperty($name);
            }
        } while ($rprop === null && $class = $class->getParentClass());

        if ($rprop === null) {
            //case-insensitive property matching
            foreach ($rc->getProperties() as $p) {
                if ((strcasecmp($p->name, $name) === 0)) {
                    $rprop = $p;
                    $class = $rc;
                    break;
                }
            }
        }
        if ($rprop !== null) {
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
        $context = $contextFactory->createForNamespace($rc->getNamespaceName(), file_get_contents($rc->getFileName()));
        $block = $factory->create($docblock, $context);
        foreach ($block->getTags() as $tag) {
            if ($tag instanceof Var_) {
                $type = $tag->getType()->__toString();
                $type = explode('|', $type)[0]; // SecurityScheme.php string|non-empty-array<string>
                $annotations[$tag->getName()][] = $type;
            }
        }
        return $annotations;
    }
}
