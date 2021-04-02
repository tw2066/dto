<?php

namespace Hyperf\DTO;

use Hyperf\Di\MethodDefinitionCollectorInterface;
use Hyperf\DTO\Exception\DTOException;
use Hyperf\DTO\Scan\PropertyManager;
use Hyperf\DTO\Scan\ValidationManager;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\ValidationException;

class ValidationDTO
{
    /**
     * @var MethodDefinitionCollectorInterface|mixed
     */
    private $methodDefinitionCollector;
    /**
     * @var ValidatorFactoryInterface|mixed
     */
    private $validationFactory;

    public function __construct()
    {
        $container = ApplicationContext::getContainer();
        $this->validationFactory =$container->get(ValidatorFactoryInterface::class);
        $this->methodDefinitionCollector = $container->get(MethodDefinitionCollectorInterface::class);
    }


    /**
     * validate
     * @param $className
     * @param $data
     */
    public function validateResolved(string $className,$data)
    {
        if(!is_array($data)){
            throw new DTOException('class:'.$className.' data must be object or array');
        }
        $notSimplePropertyArr = PropertyManager::getPropertyAndNotSimpleType($className);
        foreach ($notSimplePropertyArr as $fieldName=>$property) {
            if(!empty($data[$fieldName])){
                $this->validateResolved($property->className,$data[$fieldName]);
            }
        }
        if(empty(ValidationManager::getRule($className))){
            return;
        }

        $validator = $this->validationFactory->make(
            $data,
            ValidationManager::getRule($className),
            ValidationManager::getMessages($className),
        );
        if ($validator->fails()){
            throw new ValidationException($validator);
        }
    }
}