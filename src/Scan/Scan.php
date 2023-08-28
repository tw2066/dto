<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

use Hyperf\Di\MethodDefinitionCollectorInterface;
use Hyperf\DTO\DtoCommon;
use Psr\Container\ContainerInterface;
use ReflectionException;

class Scan
{
    protected static array $scanClassArray = [];

    public function __construct(
        protected ContainerInterface $container,
        protected MethodDefinitionCollectorInterface $methodDefinitionCollector,
        protected DtoCommon $dtoCommon,
        protected PropertyManager $propertyManager,
        protected ValidationManager $validationManager,
        protected MethodParametersManager $methodParametersManager,
    ) {
    }

    /**
     * 扫描控制器中的方法.
     * @throws ReflectionException
     */
    public function scan(string $className, string $methodName): void
    {
        // 设置方法中的参数.
        $this->methodParametersManager->setMethodParameters($className, $methodName);
        $definitionArr = $this->methodDefinitionCollector->getParameters($className, $methodName);
        $definitionArr[] = $this->methodDefinitionCollector->getReturnType($className, $methodName);
        foreach ($definitionArr as $definition) {
            $parameterClassName = $definition->getName();
            if ($this->container->has($parameterClassName)) {
                $this->scanClass($parameterClassName);
            }
        }
    }

    public function clearScanClassArray(): void
    {
        self::$scanClassArray = [];
    }

    /**
     * 扫描类.
     */
    public function scanClass(string $className): void
    {
        if (in_array($className, self::$scanClassArray)) {
            return;
        }
        self::$scanClassArray[] = $className;
        $propertyArr = $this->propertyManager->getPropertyByClass($className);
        foreach ($propertyArr as $fieldName => $property) {
            $this->validationManager->generateValidation($className, $fieldName);
        }
    }
}
