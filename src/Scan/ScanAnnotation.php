<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

use Hyperf\ApiDocs\ApiAnnotation;
use Hyperf\Di\MethodDefinitionCollectorInterface;
use Hyperf\Di\ReflectionManager;
use Hyperf\DTO\Annotation\Validation\BaseValidation;
use Hyperf\Utils\ApplicationContext;
use JsonMapper;

class ScanAnnotation extends JsonMapper
{
    private static array $scanClassArray = [];

    /**
     * @var MethodDefinitionCollectorInterface|mixed
     */
    private $methodDefinitionCollector;

    /**
     * @var \Psr\Container\ContainerInterface
     */
    private $container;

    public function __construct()
    {
        $this->container = ApplicationContext::getContainer();
        $this->methodDefinitionCollector = $this->container->get(MethodDefinitionCollectorInterface::class);
    }

    public function scan($className, $methodName)
    {
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

    public function scanClass(string $className)
    {
        if (in_array($className, self::$scanClassArray)) {
            return;
        }
        self::$scanClassArray[] = $className;

        $rc = ReflectionManager::reflectClass($className);
        $strNs = $rc->getNamespaceName();
        foreach ($rc->getProperties() ?? [] as $reflectionProperty) {
            $propertyClassName = $type = $this->getTypeName($reflectionProperty);
            $fieldName = $reflectionProperty->getName();
            $isSimpleType = true;
            if ($type == 'array') {
                $arrType = null;
                $docblock = $reflectionProperty->getDocComment();
                $annotations = static::parseAnnotations($docblock);
                if (!empty($annotations)) {
                    //support "@var type description"
                    [$varType] = explode(' ', $annotations['var'][0]);
                    $varType = $this->getFullNamespace($varType, $strNs);
                    if ($this->isArrayOfType($varType)) {
                        $arrType = substr($varType, 0, -2);
                        $isSimpleType = $this->isSimpleType($arrType);
                        if (!$this->isSimpleType($arrType) && $this->container->has($arrType)) {
                            $this->scanClass($arrType);
                            PropertyManager::setNotSimpleClass($className);
                        }
                    }
                }
                $propertyClassName = $arrType;
            }
            if (!$this->isSimpleType($type)) {
                $this->scanClass($type);
                $isSimpleType = false;
                $propertyClassName = $type;
                PropertyManager::setNotSimpleClass($className);
            }

            $property = new Property();
            $property->type = $type;
            $property->isSimpleType = $isSimpleType;
            $property->className = $propertyClassName;
            PropertyManager::setContent($className, $fieldName, $property);

            $this->makeValidation($className, $fieldName);
        }
    }

    /**
     * makeValidation.
     */
    protected function makeValidation(string $className, string $fieldName)
    {
        /** @var BaseValidation[] $validation */
        $validationArr = [];
        $propertyReflectionPropertyArr = ApiAnnotation::propertyMetadata($className, $fieldName);
        foreach ($propertyReflectionPropertyArr as $propertyReflectionProperty) {
            if ($propertyReflectionProperty instanceof BaseValidation) {
                $validationArr[] = $propertyReflectionProperty;
            }
        }
        $rule = null;
        foreach ($validationArr as $validation) {
            if (empty($validation->rule)) {
                continue;
            }
            $rule .= $validation->rule . '|';
            if (empty($validation->messages)) {
                continue;
            }
            $messagesRule = explode(':', $validation->rule)[0];
            $key = $fieldName . '.' . $messagesRule;
            ValidationManager::setMessages($className, $key, $validation->messages);
        }
        !empty($rule) && ValidationManager::setRule($className, $fieldName, trim($rule, '|'));
    }

    protected function getTypeName(\ReflectionProperty $rp)
    {
        try {
            $type = $rp->getType()->getName();
        } catch (\Throwable $throwable) {
            $type = 'string';
        }
        return $type;
    }
}
