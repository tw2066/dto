<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\TranslatorInterface;
use Hyperf\Contract\ValidatorInterface;
use Hyperf\DTO\Exception\DtoException;
use Hyperf\DTO\Mapping\MappingContext;
use Hyperf\DTO\Mapping\ResolvedInput;
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

    private JsonMapper $mapper;

    private ?TranslatorInterface $translator = null;

    private array $externalAttributes = [];

    private array $validatorAttributes = [];

    private array $validationData = [];

    public function __construct(
        protected PropertyManager $propertyManager,
        protected ValidationManager $validationManager,
        ?JsonMapper $mapper = null,
    ) {
        $this->mapper = $mapper ?? Mapper::getJsonMapper();
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
        $this->validateResolvedInput($this->prepare($className, $data));
    }

    /**
     * @internal 由 JsonMapper 解析一次，供验证、映射或 RPC 共用。
     */
    public function prepare(string $className, mixed $data, ?MappingContext $context = null): ResolvedInput
    {
        return $this->mapper->resolveInput($data, $className, $context);
    }

    /**
     * @internal 验证同一 ResolvedInput，不再解析输入键。
     */
    public function validateResolvedInput(ResolvedInput $input): void
    {
        if ($this->validationFactory === null) {
            return;
        }
        $this->validateResolved($input->className, $input->data);
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
     * 解析并验证规范化数据.
     *
     * @throws DtoException 当数据不是数组或对象时抛出
     * @throws ValidationException 当验证失败时抛出
     */
    protected function validateResolved(string $className, mixed $data): void
    {
        if (! is_array($data)) {
            throw new DtoException("Class: {$className} - data must be object or array");
        }

        $validArr = $this->validationData[$className] ??= $this->canonicalizeValidationData(
            $className,
            $this->validationManager->getData($className)
        );
        if (! empty($validArr)) {
            $data = $this->withCanonicalConfirmationFields($className, $data, $validArr);
            $externalAttributes = $this->getExternalAttributes($className, $validArr);
            $validator = $this->validationFactory->make(
                $data,
                $validArr['rule'],
                $validArr['messages'] ?? [],
                $this->getValidatorAttributes($className, $externalAttributes, $validArr)
            );
            if ($validator->fails()) {
                throw $this->buildValidationException($this->externalizeValidator($className, $validator, $externalAttributes));
            }
        }

        // 类自身无规则时也要递归，嵌套类的规则独立生效。
        $notSimplePropertyArr = $this->propertyManager->getPropertyAndNotSimpleType($className);
        foreach ($notSimplePropertyArr as $fieldName => $property) {
            $value = $data[$fieldName] ?? null;
            if (array_key_exists($fieldName, $data) && (is_array($value) || ! empty($value))) {
                if ($property->isClassArray()) {
                    $this->validateClassArray($property->arrClassName, $value, max(1, $property->arrayDepth));
                } elseif ($property->className != null) {
                    $this->validateResolved($property->className, $value);
                }
            }
        }
    }

    private function validateClassArray(string $className, mixed $value, int $remainingDepth): void
    {
        if (! is_array($value)) {
            // 非数组值（标量、枚举、对象）没有可递归的 DTO 规则，交给父级规则处理。
            return;
        }
        foreach ($value as $item) {
            if (! is_array($item)) {
                // 枚举/标量/对象叶子在验证阶段不是 canonical DTO，不参与递归验证。
                continue;
            }
            if ($remainingDepth > 1) {
                $this->validateClassArray($className, $item, $remainingDepth - 1);
            } else {
                $this->validateResolved($className, $item);
            }
        }
    }

    private function canonicalizeValidationData(string $className, array $validArr): array
    {
        foreach ($validArr['rule'] ?? [] as $ruleKey => $rules) {
            $canonicalKey = $this->propertyManager->getCanonicalFieldPath($className, $ruleKey);
            if ($canonicalKey === $ruleKey) {
                continue;
            }
            $validArr['rule'][$canonicalKey] = array_merge($validArr['rule'][$canonicalKey] ?? [], $rules);
            unset($validArr['rule'][$ruleKey]);
            foreach (['messages', 'attributes'] as $type) {
                foreach ($validArr[$type] ?? [] as $key => $value) {
                    if ($key !== $ruleKey && ! str_starts_with($key, $ruleKey . '.')) {
                        continue;
                    }
                    $suffix = substr($key, strlen($ruleKey));
                    unset($validArr[$type][$key]);
                    $validArr[$type][$canonicalKey . $suffix] = $value;
                }
            }
        }
        return $validArr;
    }

    private function withCanonicalConfirmationFields(string $className, array $data, array $validArr): array
    {
        foreach ($validArr['rule'] ?? [] as $field => $rules) {
            if (! in_array('confirmed', $rules, true)) {
                continue;
            }
            $externalField = $this->propertyManager->getExternalFieldPath($className, $field);
            $externalConfirmation = $externalField . '_confirmation';
            if (array_key_exists($externalConfirmation, $data)) {
                // 外部确认字段与主字段别名一致，并按外部契约优先。
                $data[$field . '_confirmation'] = $data[$externalConfirmation];
            }
        }
        return $data;
    }

    private function getExternalAttributes(string $className, array $validArr): array
    {
        if (! isset($this->externalAttributes[$className])) {
            $this->externalAttributes[$className] = $this->propertyManager->getExternalAttributes(
                $className,
                array_keys($validArr['rule'] ?? [])
            );
        }
        return $this->externalAttributes[$className];
    }

    private function getValidatorAttributes(string $className, array $externalAttributes, array $validArr): array
    {
        $attributes = $externalAttributes;
        if ($this->translator !== null) {
            $cacheKey = $className . ':' . $this->translator->getLocale();
            if (! isset($this->validatorAttributes[$cacheKey])) {
                foreach ($attributes as $field => $externalField) {
                    // 外部别名保持基线优先级，同时允许项目按 PHP 属性名配置翻译。
                    foreach ([$externalField, $field] as $translationField) {
                        $translationKey = 'validation.attributes.' . $translationField;
                        $translation = $this->translator->trans($translationKey);
                        if (is_string($translation) && $translation !== $translationKey) {
                            $attributes[$field] = $translation;
                            break;
                        }
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

    private function externalizeValidator(string $className, ValidatorInterface $validator, array $externalAttributes): ValidatorInterface
    {
        if ($externalAttributes === []) {
            return $validator;
        }

        $messages = $validator->errors()->messages();
        $externalMessages = [];
        foreach ($messages as $field => $fieldMessages) {
            $externalField = $externalAttributes[$field]
                ?? $this->propertyManager->getExternalFieldPath($className, $field);
            foreach ($fieldMessages as $message) {
                $externalMessages[$externalField][] = $message;
            }
        }
        if ($messages !== $externalMessages) {
            // 保留原 validator 的 failedRules、数据和后续回调，只替换对外错误键。
            $messageBag = $validator->errors();
            $messagesProperty = new \ReflectionProperty($messageBag, 'messages');
            $messagesProperty->setAccessible(true);
            $messagesProperty->setValue($messageBag, $externalMessages);
        }
        return $validator;
    }
}
