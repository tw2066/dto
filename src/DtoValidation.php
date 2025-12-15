<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Context\ApplicationContext;
use Hyperf\DTO\Exception\DtoException;
use Hyperf\DTO\Scan\PropertyManager;
use Hyperf\DTO\Scan\ValidationManager;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\ValidationException;

class DtoValidation
{
    public static bool $isValidationCustomAttributes = false;

    private ?ValidatorFactoryInterface $validationFactory = null;

    public function __construct(protected PropertyManager $propertyManager,protected ValidationManager $validationManager)
    {
        $container = ApplicationContext::getContainer();
        if ($container->has(ValidatorFactoryInterface::class)) {
            $this->validationFactory = $container->get(ValidatorFactoryInterface::class);
        }
    }

    public function validate(string $className, $data): void
    {
        if ($this->validationFactory == null) {
            return;
        }
        $this->validateResolved($className, $data);
    }

    /**
     * Validate data recursively with depth limit.
     *
     * @param string $className The DTO class name
     * @param mixed $data The data to validate
     * @throws DtoException|ValidationException
     */
    private function validateResolved(string $className, mixed $data): void
    {
        if (! is_array($data)) {
            throw new DtoException("Class: {$className} - data must be object or array");
        }

        $validArr = $this->validationManager->getData($className);
        if (empty($validArr)) {
            return;
        }
        $validator = $this->validationFactory->make(
            $data,
            $validArr['rule'],
            $validArr['messages'] ?? [],
            static::$isValidationCustomAttributes ? ($validArr['attributes'] ?? []) : []
        );
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Recursively validate nested objects and arrays
        $notSimplePropertyArr = $this->propertyManager->getPropertyAndNotSimpleType($className);
        foreach ($notSimplePropertyArr as $fieldName => $property) {
            if (! empty($data[$fieldName])) {
                if ($property->isClassArray()) {
                    foreach ($data[$fieldName] as $item) {
                        $this->validateResolved($property->arrClassName, $item);
                    }
                } elseif ($property->className != null) {
                    $this->validateResolved($property->className, $data[$fieldName]);
                }
            }
        }
    }
}
