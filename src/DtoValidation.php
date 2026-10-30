<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\TranslatorInterface;
use Hyperf\Contract\ValidatorInterface;
use Hyperf\DTO\Exception\DtoException;
use Hyperf\DTO\Scan\Property;
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

    private ?ValidatorFactoryInterface $validationFactory = null;

    private ?TranslatorInterface $translator = null;

    private array $validatorAttributes = [];

    /** @var array<string, true> 已生成过规则的类，避免重复扫描注解 */
    private array $ensuredRules = [];

    private ?JsonMapper $mapper = null;

    public function __construct(protected PropertyManager $propertyManager, protected ValidationManager $validationManager)
    {
        $container = ApplicationContext::getContainer();
        if ($container->has(ValidatorFactoryInterface::class)) {
            $this->validationFactory = $container->get(ValidatorFactoryInterface::class);
            if (method_exists($this->validationFactory, 'getTranslator')) {
                $translator = $this->validationFactory->getTranslator();
                $this->translator = $translator instanceof TranslatorInterface ? $translator : null;
            }
        }
    }

    /**
     * 验证 DTO 数据.
     */
    public function validate(string $className, $data): void
    {
        if ($this->validationFactory === null) {
            return;
        }
        $this->validateResolved($className, $data ?? []);
    }

    /**
     * 构建验证异常.
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
     * 按外部 primaryKey 验证原始输入，并递归处理嵌套 DTO。
     *
     * @throws DtoException 当数据不是数组时抛出
     * @throws ValidationException 当验证失败时抛出
     */
    protected function validateResolved(string $className, mixed $data): void
    {
        if (! is_array($data)) {
            throw new DtoException("Class: {$className} - data must be array");
        }

        $this->ensureRules($className);

        $validArr = $this->validationManager->getData($className);
        if (! empty($validArr)) {
            $validator = $this->validationFactory->make(
                $data,
                $validArr['rule'],
                $validArr['messages'] ?? [],
                $this->getValidatorAttributes($className, $validArr)
            );
            if ($validator->fails()) {
                throw $this->buildValidationException($validator);
            }
        }

        // 类自身无规则时也要递归，嵌套类的规则独立生效。
        $notSimplePropertyArr = $this->propertyManager->getPropertyAndNotSimpleType($className);
        foreach ($notSimplePropertyArr as $fieldName => $property) {
            $this->validateNestedProperty($property, $property->alias ?? $fieldName, $data);
        }
        // setter-only 字段没有声明属性、不在 notSimpleClass 中，由映射计划补充发现。
        foreach ($this->mapper()->getSetterOnlyNestedProperties($className) as $dataKey => $property) {
            $this->validateNestedProperty($property, $dataKey, $data);
        }
    }

    private function validateNestedProperty(Property $property, string $dataKey, array $data): void
    {
        if (! array_key_exists($dataKey, $data)) {
            return;
        }
        $value = $data[$dataKey];
        if ($property->isClassArray()) {
            $this->validateClassArray(
                $property->arrClassName ?? $property->arrInterfaceName,
                $value,
                max(1, $property->arrayDepth)
            );
            return;
        }
        $nestedType = $property->className ?? $property->interfaceName;
        if ($nestedType !== null && is_array($value)) {
            $resolved = $this->mapper()->resolveMappedClass($nestedType, $value);
            if ($resolved !== null) {
                $this->validateResolved($resolved, $value);
            }
        }
    }

    /**
     * 与运行时映射共用同一 JsonMapper 单例，保证验证按映射实际的 classMap 解析。
     */
    private function mapper(): JsonMapper
    {
        return $this->mapper ??= Mapper::getJsonMapper();
    }

    /**
     * 为验证阶段才发现的 classMap 具体类补生成规则。
     */
    private function ensureRules(string $className): void
    {
        if (isset($this->ensuredRules[$className])) {
            return;
        }
        foreach (array_keys($this->propertyManager->getPropertyByClass($className)) as $fieldName) {
            $this->validationManager->generateValidation($className, $fieldName);
        }
        $this->ensuredRules[$className] = true;
    }

    private function validateClassArray(string $className, mixed $value, int $remainingDepth): void
    {
        if (! is_array($value)) {
            // 非数组值（标量、枚举、对象）没有可递归的 DTO 规则，交给父级规则处理。
            return;
        }
        foreach ($value as $item) {
            if (! is_array($item)) {
                // 标量、枚举和对象叶子不参与 DTO 递归验证。
                continue;
            }
            if ($remainingDepth > 1) {
                $this->validateClassArray($className, $item, $remainingDepth - 1);
                continue;
            }
            $resolved = $this->mapper()->resolveMappedClass($className, $item);
            if ($resolved !== null) {
                $this->validateResolved($resolved, $item);
            }
        }
    }

    private function getValidatorAttributes(string $className, array $validArr): array
    {
        $attributes = [];
        if ($this->translator !== null) {
            $cacheKey = $className . ':' . $this->translator->getLocale();
            if (! isset($this->validatorAttributes[$cacheKey])) {
                foreach (array_keys($validArr['rule'] ?? []) as $field) {
                    $translationKey = 'validation.attributes.' . $field;
                    $translation = $this->translator->trans($translationKey);
                    if (is_string($translation) && $translation !== $translationKey) {
                        $attributes[$field] = $translation;
                    }
                }
                $this->validatorAttributes[$cacheKey] = $attributes;
            }
            $attributes = $this->validatorAttributes[$cacheKey];
        }
        if (static::$isValidationCustomAttributes) {
            return array_replace($attributes, $validArr['attributes'] ?? []);
        }
        return $attributes;
    }
}
