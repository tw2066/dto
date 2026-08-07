<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ValidatorInterface;
use Hyperf\DTO\Exception\DtoException;
use Hyperf\DTO\Scan\PropertyManager;
use Hyperf\DTO\Scan\ValidationManager;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\ValidationException;

/**
 * DTO 验证类
 * 负责验证 DTO 对象的数据有效性.
 */
class DtoValidation
{
    /**
     * 是否使用自定义属性名称进行验证
     */
    public static bool $isValidationCustomAttributes = false;

    /**
     * 验证工厂实例.
     */
    private ?ValidatorFactoryInterface $validationFactory = null;

    /**
     * 构造函数.
     *
     * @param PropertyManager $propertyManager 属性管理器
     * @param ValidationManager $validationManager 验证管理器
     */
    public function __construct(protected PropertyManager $propertyManager, protected ValidationManager $validationManager)
    {
        $container = ApplicationContext::getContainer();
        if ($container->has(ValidatorFactoryInterface::class)) {
            $this->validationFactory = $container->get(ValidatorFactoryInterface::class);
        }
    }

    /**
     * 验证 DTO 数据.
     *
     * @param string $className DTO 类名
     * @param mixed $data 要验证的数据
     */
    public function validate(string $className, $data): void
    {
        if ($this->validationFactory == null) {
            return;
        }
        $this->validateResolved($className, $data);
    }

    /**
     * 构建验证异常.
     *
     * @param ValidatorInterface $validator 验证器实例
     * @return ValidationException 验证异常
     */
    protected function buildValidationException(ValidatorInterface $validator): ValidationException
    {
        $message = 'The given data was invalid, error message: ' . implode(' ', $validator->errors()->all());
        $getResponseBuilder = function () use ($message) {
            /* @phpstan-ignore-next-line */
            $this->message = $message;
            return $this;
        };
        return $getResponseBuilder->call(new ValidationException($validator));
    }

    /**
     * 解析并验证数据.
     *
     * @param string $className DTO 类名
     * @param mixed $data 要验证的数据
     * @throws DtoException 当数据不是数组或对象时抛出
     * @throws ValidationException 当验证失败时抛出
     */
    protected function validateResolved(string $className, $data): void
    {
        if (! is_array($data)) {
            throw new DtoException("Class: {$className} - data must be object or array");
        }

        $validArr = $this->validationManager->getData($className);
        if (! empty($validArr)) {
            $validator = $this->validationFactory->make(
                $data,
                $validArr['rule'],
                $validArr['messages'] ?? [],
                static::$isValidationCustomAttributes ? ($validArr['attributes'] ?? []) : []
            );
            if ($validator->fails()) {
                throw $this->buildValidationException($validator);
            }
        }

        // 递归验证嵌套对象和数组（类自身无规则时也要递归，嵌套类的规则独立生效）
        $notSimplePropertyArr = $this->propertyManager->getPropertyAndNotSimpleType($className);
        foreach ($notSimplePropertyArr as $fieldName => $property) {
            // 数据键优先取 JSONField 别名（与 ValidationManager 的 rule 键一致），回退 PHP 属性名
            $dataKey = $property->alias ?? $fieldName;
            $value = $data[$dataKey] ?? $data[$fieldName] ?? null;
            if (! empty($value)) {
                if ($property->isClassArray()) {
                    foreach ($value as $item) {
                        $this->validateResolved($property->arrClassName, $item);
                    }
                } elseif ($property->className != null) {
                    $this->validateResolved($property->className, $value);
                }
            }
        }
    }
}
