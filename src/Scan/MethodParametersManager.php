<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

use Hyperf\Di\ReflectionManager;
use Hyperf\DTO\Annotation\Contracts\RequestBody;
use Hyperf\DTO\Annotation\Contracts\RequestFormData;
use Hyperf\DTO\Annotation\Contracts\RequestHeader;
use Hyperf\DTO\Annotation\Contracts\RequestQuery;
use Hyperf\DTO\Annotation\Contracts\Valid;
use Hyperf\DTO\DtoCommon;
use Hyperf\DTO\Exception\DtoException;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Types\ContextFactory;
use ReflectionClass;
use ReflectionException;

class MethodParametersManager
{
    protected static array $content = [];

    private static array $scanClassMethodArray = [];

    public function __construct(protected DtoCommon $dtoCommon, protected PropertyEnum $propertyEnum, protected PropertyManager $propertyManager)
    {
    }

    public function getMethodParameter(string $className, string $methodName, string $paramName): ?MethodParameter
    {
        $key = $this->getKey($className, $methodName, $paramName);
        if (! isset(static::$content[$key])) {
            return null;
        }

        return static::$content[$key];
    }

    /**
     * 设置方法中的参数.
     *
     * @throws ReflectionException
     */
    public function setMethodParameters(string $className, string $methodName): void
    {
        // 获取方法的反射对象
        $ref = ReflectionManager::reflectMethod($className, $methodName);
        // 获取方法上指定名称的全部注解
        $attributes = $ref->getParameters();
        $methodMark = 0;
        $headerMark = 0;
        $total = 0;
        foreach ($attributes as $attribute) {
            $methodParameters = new MethodParameter();
            $paramName = $attribute->getName();

            $mark = 0;
            if ($attribute->getAttributes(RequestQuery::class)) {
                $methodParameters->setIsRequestQuery(true);
                ++$mark;
                ++$total;
            }
            if ($attribute->getAttributes(RequestFormData::class)) {
                $methodParameters->setIsRequestFormData(true);
                ++$mark;
                ++$methodMark;
                ++$total;
            }
            if ($attribute->getAttributes(RequestBody::class)) {
                $methodParameters->setIsRequestBody(true);
                ++$mark;
                ++$methodMark;
                ++$total;
            }
            if ($attribute->getAttributes(RequestHeader::class)) {
                $methodParameters->setIsRequestHeader(true);
                ++$headerMark;
                ++$total;
            }
            if ($attribute->getAttributes(Valid::class)) {
                ++$total;
                $methodParameters->setIsValid(true);
            }
            if ($mark > 1) {
                throw new DtoException("Parameter annotation [RequestQuery RequestFormData RequestBody] cannot exist simultaneously [{$className}::{$methodName}:{$paramName}]");
            }
            if ($headerMark > 1) {
                throw new DtoException("Parameter annotation [RequestHeader] can only exist [{$className}::{$methodName}:{$paramName}]");
            }
            if ($total > 0) {
                $this->setContent($className, $methodName, $paramName, $methodParameters);
            }
        }
        if ($methodMark > 1) {
            throw new DtoException("Method annotation [RequestFormData RequestBody] cannot exist simultaneously [{$className}::{$methodName}]");
        }
    }

    public function scanClassMethodParam(string $className, string $methodName): void
    {
        $className = ltrim($className, '\\');
        if (in_array($className . '_' . $methodName, self::$scanClassMethodArray)) {
            return;
        }
        self::$scanClassMethodArray[] = $className . '_' . $methodName;

        $rc = ReflectionManager::reflectClass($className);
        $strNs = $rc->getNamespaceName();
        $reflectionMethod = $rc->getMethod($methodName);
        $reflectionParameters = $reflectionMethod->getParameters() ?? [];
        $annotations = $this->getParamType($className, $methodName);

        foreach ($reflectionParameters as $reflectionParameter) {
            $paramName = $reflectionParameter->getName();
            $isSimpleType = true;
            $phpSimpleType = null;
            $propertyClassName = null;
            $arrSimpleType = null;
            $arrClassName = null;
            $type = $this->dtoCommon->getTypeName($reflectionParameter);

            // php简单类型
            if ($this->dtoCommon->isSimpleType($type)) {
                $phpSimpleType = $type;
            }
            // 数组类型
            $propertyEnum = $this->propertyEnum->get($type);
            if ($type == 'array') {
                if (! empty($annotations)) {
                    $varType = $annotations[$paramName] ?? null;
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
                            $this->propertyManager->scanClassProperties($arrType);
                        }
                    }
                }
            } elseif ($propertyEnum) {
                $isSimpleType = false;
            } elseif (class_exists($type)) {
                $this->propertyManager->scanClassProperties($type);
                $isSimpleType = false;
                $propertyClassName = $type;
            }

            $property = new Property();
            $property->phpSimpleType = $phpSimpleType;
            $property->isSimpleType = $isSimpleType;
            $property->arrSimpleType = $arrSimpleType;
            $property->arrClassName = $arrClassName ? trim($arrClassName, '\\') : null;
            $property->className = $propertyClassName ? trim($propertyClassName, '\\') : null;
            $property->enum = $propertyEnum;

            $this->setProperty($className, $methodName, $paramName, $property);
        }
    }

    public function getProperty(string $className, string $methodName, string $paramName): ?Property
    {
        return static::$content[$className][$methodName][$paramName] ?? null;
    }

    protected function setContent(string $className, string $methodName, string $paramName, MethodParameter $method): void
    {
        $key = $this->getKey($className, $methodName, $paramName);
        if (isset(static::$content[$key])) {
            return;
        }
        static::$content[$key] = $method;
    }

    protected function getKey(string $className, string $methodName, string $paramName): string
    {
        // high query efficiency
        return $className . ':' . $methodName . ':' . $paramName;
    }

    private function setProperty(string $className, string $methodName, string $paramName, Property $property): void
    {
        static::$content[$className][$methodName][$paramName] = $property;
    }

    private function getParamType(string $className, string $methodName)
    {
        $rc = new ReflectionClass($className);
        $reflectionMethod = $rc->getMethod($methodName);
        $docblock = $reflectionMethod->getDocComment();
        if (empty($docblock)) {
            return [];
        }
        $factory = DocBlockFactory::createInstance();
        $contextFactory = new ContextFactory();
        $context = $contextFactory->createForNamespace($rc->getNamespaceName(), file_get_contents($rc->getFileName()));

        $block = $factory->create($docblock, $context);
        $annotations = [];
        foreach ($block->getTags() as $tag) {
            if ($tag instanceof Param) {
                $type = $tag->getType()->__toString();
                $variableName = $tag->getVariableName();
                $type = explode('|', $type)[0];
                $annotations[$variableName] = $type;
            }
        }

        return $annotations;
    }
}
