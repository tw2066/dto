<?php

namespace Hyperf\DTO;

use Hyperf\Di\MethodDefinitionCollectorInterface;
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
    public function validateResolved($className,$data)
    {
        $notSimplePropertyArr = PropertyManager::getPropertyAndNotSimpleType($className);
        foreach ($notSimplePropertyArr as $fieldName=>$property) {
            if(!empty($data[$fieldName])){
                if($property->type == 'array'){
                    foreach ($data[$fieldName] as $item) {
                        $this->validateResolved($property->className,$item);
                    }
                }else{
                    $this->validateResolved($property->className,$data[$fieldName]);
                }
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