<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ValidatorInterface;
use Hyperf\Di\ReflectionManager;
use Hyperf\DTO\Exception\DtoException;
use Hyperf\DTO\Scan\PropertyManager;
use Hyperf\DTO\Scan\ValidationManager;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\ValidationException;
use ReflectionMethod;
use ReflectionProperty;

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

    private static array $keyMapCache = [];

    private DtoCommon $dtoCommon;

    /**
     * 构造函数.
     *
     * @param PropertyManager $propertyManager 属性管理器
     * @param ValidationManager $validationManager 验证管理器
     */
    public function __construct(protected PropertyManager $propertyManager, protected ValidationManager $validationManager)
    {
        $this->dtoCommon = new DtoCommon();
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

        // 与映射器一致：safe key 后依次尝试别名、公共 setter 和公有属性。
        if (! isset(static::$keyMapCache[$className])) {
            $propertyArr = $this->propertyManager->getPropertyByClass($className);
            $reflectionClass = ReflectionManager::reflectClass($className);
            $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);
            $publicProperties = [];
            foreach ($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC) as $reflectionProperty) {
                $publicProperties[$reflectionProperty->getName()] = true;
            }

            $exactKeyMap = [];
            $setterKeyMap = [];
            $propertyKeyMap = [];
            foreach ($propertyArr as $fieldName => $property) {
                $ruleKey = $property->alias ?? $fieldName;
                $hasAlias = $property->alias !== null && $property->alias !== $fieldName;
                if ($hasAlias) {
                    $exactKeyMap[$fieldName] = $ruleKey;
                    $exactKeyMap[$property->alias] = $ruleKey;
                    $exactKeyMap[$this->dtoCommon->getSafeName($property->alias)] = $ruleKey;
                }

                foreach ($methods as $method) {
                    $methodName = $method->getName();
                    if (! str_starts_with($methodName, 'set')) {
                        continue;
                    }
                    $suffix = substr($methodName, 3);
                    if (strcasecmp($suffix, $fieldName) !== 0 || $this->dtoCommon->getSetterName($suffix) !== $methodName) {
                        continue;
                    }
                    $exactKeyMap[$fieldName] = $ruleKey;
                    $setterKeyMap[$methodName] = $ruleKey;
                    break;
                }

                if (isset($publicProperties[$fieldName]) && $this->dtoCommon->getSafeName('-' . $fieldName) === $fieldName) {
                    $propertyKeyMap[$fieldName] = $ruleKey;
                }
            }
            static::$keyMapCache[$className] = [$exactKeyMap, $setterKeyMap, $propertyKeyMap];
        }
        [$exactKeyMap, $setterKeyMap, $propertyKeyMap] = static::$keyMapCache[$className];

        // 无别名或键变体时，精确声明键无需归一化。
        if ($exactKeyMap !== [] || $setterKeyMap !== [] || $propertyKeyMap !== []) {
            $keyOwners = [];
            $normalizedValues = [];
            foreach ($data as $key => $value) {
                if (! is_string($key)) {
                    continue;
                }
                $ruleKey = $exactKeyMap[$key] ?? null;
                if ($ruleKey === null) {
                    $safeKey = $this->dtoCommon->getSafeName($key);
                    $ruleKey = $exactKeyMap[$safeKey] ?? null;
                    if ($ruleKey === null) {
                        $setter = $this->dtoCommon->getSetterName($safeKey);
                        $ruleKey = $setterKeyMap[$setter] ?? $propertyKeyMap[$safeKey] ?? null;
                    }
                }
                if ($ruleKey === null) {
                    continue;
                }
                if (isset($keyOwners[$ruleKey]) && $keyOwners[$ruleKey] !== $key) {
                    $validator = $this->validationFactory->make($data, []);
                    $validator->errors()->add($ruleKey, 'The ' . $keyOwners[$ruleKey] . ' and ' . $key . ' fields cannot both be present.');
                    throw $this->buildValidationException($validator);
                }
                $keyOwners[$ruleKey] = $key;
                if ($key !== $ruleKey) {
                    $normalizedValues[$ruleKey] = $value;
                }
            }
            foreach ($normalizedValues as $key => $value) {
                $data[$key] = $value;
            }
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
            // 使用归一化后的 JSONField 别名或 PHP 属性名键
            $dataKey = $property->alias ?? $fieldName;
            $value = $data[$dataKey] ?? null;
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
