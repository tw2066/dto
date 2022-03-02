<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use JsonMapper;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Types\ContextFactory;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;

class JsonMapperDto extends JsonMapper
{
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
    protected function inspectProperty(ReflectionClass $rc, $name)
    {
        //try setter method first
        $setter = 'set' . $this->getCamelCaseName($name);

        if ($rc->hasMethod($setter)) {
            $rmeth = $rc->getMethod($setter);
            if ($rmeth->isPublic() || $this->bIgnoreVisibility) {
                $isNullable = false;
                $rparams = $rmeth->getParameters();
                if (count($rparams) > 0) {
                    $isNullable = $rparams[0]->allowsNull();
                    $ptype = $rparams[0]->getType();
                    if ($ptype !== null) {
                        if ($ptype instanceof ReflectionNamedType) {
                            $typeName = $ptype->getName();
                        }
                        if ($ptype instanceof ReflectionUnionType
                            || ! $ptype->isBuiltin()
                        ) {
                            $typeName = '\\' . $typeName;
                        }
                        //allow overriding an "array" type hint
                        // with a more specific class in the docblock
                        if ($typeName !== 'array') {
                            return [
                                true, $rmeth,
                                $typeName,
                                $isNullable,
                            ];
                        }
                    }
                }

                $docblock = $rmeth->getDocComment();
                $annotations = static::parseAnnotations($docblock);

                if (! isset($annotations['param'][0])) {
                    return [true, $rmeth, null, $isNullable];
                }
                [$type] = explode(' ', trim($annotations['param'][0]));
                return [true, $rmeth, $type, $this->isNullable($type)];
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
                    break;
                }
            }
        }
        if ($rprop !== null) {
            if ($rprop->isPublic() || $this->bIgnoreVisibility) {
                $docblock = $rprop->getDocComment();
                $annotations = static::parseAnnotations2($rc, $docblock);

                if (! isset($annotations['var'][0])) {
                    // If there is no annotations (higher priority) inspect
                    // if there's a scalar type being defined
                    if (PHP_VERSION_ID >= 70400 && $rprop->hasType()) {
                        $rPropType = $rprop->getType();
                        $propTypeName = $rPropType->getName();

                        if ($this->isSimpleType($propTypeName)) {
                            return [
                                true,
                                $rprop,
                                $propTypeName,
                                $rPropType->allowsNull(),
                            ];
                        }

                        return [
                            true,
                            $rprop,
                            '\\' . $propTypeName,
                            $rPropType->allowsNull(),
                        ];
                    }

                    return [true, $rprop, null, false];
                }

                //support "@var type description"
                [$type] = explode(' ', $annotations['var'][0]);

                return [true, $rprop, $type, $this->isNullable($type)];
            }
            //no setter, private property
            return [true, null, null, false];
        }

        //no setter, no property
        return [false, null, null, false];
    }

    /**
     * Copied from PHPUnit 3.7.29, Util/Test.php.
     *
     * @param false|string $docblock Full method docblock
     *
     * @return array Array of arrays.
     *               Key is the "@"-name like "param",
     *               each value is an array of the rest of the @-lines
     */
    protected static function parseAnnotations2(ReflectionClass $rc, $docblock): array
    {
        if (! is_string($docblock)) {
            return [];
        }
        $factory = DocBlockFactory::createInstance();
        $contextFactory = new ContextFactory();
        $context = $contextFactory->createForNamespace($rc->getNamespaceName(), file_get_contents($rc->getFileName()));
        $block = $factory->create($docblock, $context);
        $annotations = [];
        /** @var Var_ $tag */
        foreach ($block->getTags() as $tag) {
            $annotations[$tag->getName()][] = $tag->getType()->__toString();
        }
        return $annotations;
    }
}
