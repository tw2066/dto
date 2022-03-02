<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

use Hyperf\ApiDocs\Annotation\ApiModelProperty;
use Hyperf\Di\MethodDefinitionCollectorInterface;
use Hyperf\Di\ReflectionManager;
use Hyperf\DTO\Annotation\Contracts\RequestBody;
use Hyperf\DTO\Annotation\Contracts\RequestFormData;
use Hyperf\DTO\Annotation\Contracts\RequestQuery;
use Hyperf\DTO\Annotation\Contracts\Valid;
use Hyperf\DTO\Annotation\Validation\BaseValidation;
use Hyperf\DTO\ApiAnnotation;
use Hyperf\DTO\Exception\DtoException;
use Hyperf\DTO\JsonMapperDto;
use Psr\Container\ContainerInterface;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;

class ScanAnnotation extends JsonMapperDto
{
    private static array $scanClassArray = [];

    private MethodDefinitionCollectorInterface $methodDefinitionCollector;

    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->methodDefinitionCollector = $this->container->get(MethodDefinitionCollectorInterface::class);
    }

    /**
     * 扫描控制器中的方法.
     * @param $className
     * @param $methodName
     * @throws ReflectionException
     */
    public function scan($className, $methodName)
    {
        $this->setMethodParameters($className, $methodName);
        $definitionParamArr = $this->methodDefinitionCollector->getParameters($className, $methodName);
        $definitionReturn = $this->methodDefinitionCollector->getReturnType($className, $methodName);
        array_push($definitionParamArr, $definitionReturn);
        foreach ($definitionParamArr as $definition) {
            $parameterClassName = $definition->getName();
            if ($this->container->has($parameterClassName)) {
                $this->scanClass($parameterClassName);
            }
        }
    }

    public function clearScanClassArray()
    {
        self::$scanClassArray = [];
    }

    /**
     * 扫描类.
     */
    public function scanClass(string $className)
    {
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
            $type = $this->getTypeName($reflectionProperty);
            //php简单类型
            if ($this->isSimpleType($type)) {
                $phpSimpleType = $type;
            }
            //数组类型
            if ($type == 'array') {
                $docblock = $reflectionProperty->getDocComment();
                $annotations = static::parseAnnotations2($rc, $docblock);
                if (! empty($annotations)) {
                    //support "@var type description"
                    [$varType] = explode(' ', $annotations['var'][0]);
                    $varType = $this->getFullNamespace($varType, $strNs);
                    //数组类型
                    if ($this->isArrayOfType($varType)) {
                        $isSimpleType = false;
                        $arrType = substr($varType, 0, -2);
                        //数组的简单类型 eg: int[]  string[]
                        if ($this->isSimpleType($arrType)) {
                            $arrSimpleType = $arrType;
                        } elseif (class_exists($arrType)) {
                            $arrClassName = $arrType;
                            PropertyManager::setNotSimpleClass($className);
                            $this->scanClass($arrType);
                        }
                    }
                }
            } elseif (class_exists($type)) {
                $this->scanClass($type);
                $isSimpleType = false;
                $propertyClassName = $type;
                PropertyManager::setNotSimpleClass($className);
            }

            $property = new Property();
            $property->phpSimpleType = $phpSimpleType;
            $property->isSimpleType = $isSimpleType;
            $property->arrSimpleType = $arrSimpleType;
            $property->arrClassName = $arrClassName ? trim($arrClassName, '\\') : null;
            $property->className = $propertyClassName ? trim($propertyClassName, '\\') : null;

            PropertyManager::setProperty($className, $fieldName, $property);
            $this->generateValidation($className, $fieldName, $property);
        }
    }

    /**
     * generateValidation.
     */
    protected function generateValidation(string $className, string $fieldName, Property $property)
    {
        /** @var BaseValidation[] $validation */
        $validationArr = [];
        $annotationArray = ApiAnnotation::getClassProperty($className, $fieldName);

        foreach ($annotationArray as $annotation) {
            if ($annotation instanceof BaseValidation) {
                $validationArr[] = $annotation;
            }
        }
        $ruleArray = [];
        foreach ($validationArr as $validation) {
            if (empty($validation->getRule())) {
                continue;
            }
            $ruleArray[] = $validation->getRule();
            if (empty($validation->messages)) {
                continue;
            }
            [$messagesRule,] = explode(':', $validation->getRule());
            $key = $fieldName . '.' . $messagesRule;
            ValidationManager::setMessages($className, $key, $validation->messages);
        }
        if (! empty($ruleArray)) {
            ValidationManager::setRule($className, $fieldName, $ruleArray);
            foreach ($annotationArray as $annotation) {
                if ($annotation instanceof ApiModelProperty && ! empty($annotation->value)) {
                    ValidationManager::setAttributes($className, $fieldName, $annotation->value);
                }
            }
        }
    }

    protected function getTypeName(ReflectionProperty $rp): string
    {
        try {
            $type = $rp->getType()->getName();
        } catch (Throwable) {
            $type = 'string';
        }
        return $type;
    }

    /**
     * 设置方法中的参数.
     * @param $className
     * @param $methodName
     * @throws ReflectionException
     */
    private function setMethodParameters($className, $methodName)
    {
        // 获取方法的反射对象
        $ref = new ReflectionMethod($className . '::' . $methodName);
        // 获取方法上指定名称的全部注解
        $attributes = $ref->getParameters();
        $methodMark = 0;
        foreach ($attributes as $attribute) {
            $methodParameters = new MethodParameter();
            $paramName = $attribute->getName();
            $mark = 0;
            if ($attribute->getAttributes(RequestQuery::class)) {
                $methodParameters->setIsRequestQuery(true);
                ++$mark;
            }
            if ($attribute->getAttributes(RequestFormData::class)) {
                $methodParameters->setIsRequestFormData(true);
                ++$mark;
                ++$methodMark;
            }
            if ($attribute->getAttributes(RequestBody::class)) {
                $methodParameters->setIsRequestBody(true);
                ++$mark;
                ++$methodMark;
            }
            if ($attribute->getAttributes(Valid::class)) {
                $methodParameters->setIsValid(true);
            }
            if ($mark > 1) {
                throw new DtoException("Parameter annotation [RequestQuery RequestFormData RequestBody] cannot exist simultaneously [{$className}::{$methodName}:{$paramName}]");
            }
            MethodParametersManager::setContent($className, $methodName, $paramName, $methodParameters);
        }
        if ($methodMark > 1) {
            throw new DtoException("Method annotation [RequestFormData RequestBody] cannot exist simultaneously [{$className}::{$methodName}]");
        }
    }
}
