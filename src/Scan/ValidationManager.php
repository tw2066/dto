<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

use Hyperf\ApiDocs\Annotation\ApiModelProperty;
use Hyperf\Di\Annotation\MultipleAnnotation;
use Hyperf\DTO\Annotation\JSONField;
use Hyperf\DTO\Annotation\Validation\BaseValidation;
use Hyperf\DTO\ApiAnnotation;
use Hyperf\Stringable\Str;

/**
 * Manager for handling validation rule generation and storage.
 */
class ValidationManager
{
    protected static array $content = [];

    /**
     * 已完成整类规则生成的类；静态共享，避免请求期对启动期已扫描类重复生成。
     *
     * @var array<string, true>
     */
    protected static array $generated = [];

    /**
     * Get validation data for a class.
     *
     * @param string $className The class name
     * @return array Validation rules, messages and attributes
     */
    public function getData(string $className): array
    {
        return static::$content[$className] ?? [];
    }

    /**
     * 该类是否已完成整类规则生成。
     */
    public function isGenerated(string $className): bool
    {
        return isset(static::$generated[$className]);
    }

    /**
     * 标记该类已完成整类规则生成；只能在所有字段生成成功后调用。
     */
    public function markGenerated(string $className): void
    {
        static::$generated[$className] = true;
    }

    /**
     * Generate validation rules for a class property.
     *
     * @param string $className The class name
     * @param string $fieldName The property name
     */
    public function generateValidation(string $className, string $fieldName): void
    {
        /** @var BaseValidation[] $validation */
        $validationArr = [];
        $allAnnotationArray = ApiAnnotation::getClassProperty($className, $fieldName);
        $aliasName = null;
        foreach ($allAnnotationArray as $multipleAnnotation) {
            if ($multipleAnnotation instanceof JSONField) {
                $aliasName = $multipleAnnotation->name;
            }
            if (! $multipleAnnotation instanceof MultipleAnnotation) {
                continue;
            }
            $annotationArray = $multipleAnnotation->toAnnotations();
            foreach ($annotationArray as $annotation) {
                if ($annotation instanceof BaseValidation) {
                    $validationArr[] = $annotation;
                }
            }
        }

        if (empty($validationArr)) {
            return;
        }

        $ruleArray = [];
        $lastRuleKey = $fieldName;
        foreach ($validationArr as $validation) {
            if (empty($validation->getRule())) {
                continue;
            }
            $validation->setFieldName($aliasName ?? $fieldName);
            // Support custom key for nested validation (e.g., 'items.*')
            // 注意: 用局部变量，避免 customKey 污染同一属性后续注解的键
            $ruleKey = $validation->getCustomKey() ?: $validation->getFieldName();
            $lastRuleKey = $ruleKey;
            $rule = $validation->getRule();

            $isRegexRule = is_string($rule) && (
                str_starts_with($rule, 'regex:') || str_starts_with($rule, 'not_regex:')
            );
            // Parse pipe-separated rules (e.g., "required|date|after:start_date")
            // Or array-style rules (e.g., ['int', 'min:5'])
            if (is_array($rule)) {
                foreach ($rule as $item) {
                    $ruleArray[$ruleKey][] = $item;
                }
            } elseif ($isRegexRule) {
                // 正则中的 | 是交替语法，不是验证规则分隔符。
                $ruleArray[$ruleKey][] = $rule;
            } elseif (is_string($rule) && Str::contains($rule, '|')) {
                $ruleArr = explode('|', $rule);
                foreach ($ruleArr as $item) {
                    $ruleArray[$ruleKey][] = $item;
                }
            } else {
                $ruleArray[$ruleKey][] = $rule;
            }

            if (empty($validation->messages)) {
                continue;
            }
            if ($isRegexRule) {
                [$messageRule] = explode(':', $rule, 2);
                $messageRuleArr = [$messageRule];
            } else {
                $ruleValue = $validation->getRule();
                if (is_array($ruleValue)) {
                    $ruleString = implode('|', $ruleValue);
                } else {
                    $ruleString = (string) $ruleValue;
                }
                [$messageRules] = explode(':', $ruleString);
                $messageRuleArr = explode('|', $messageRules);
            }
            if (is_array($validation->messages)) {
                $messageArr = $validation->messages;
            } else {
                $messageArr = explode('|', $validation->messages);
            }
            foreach ($messageRuleArr as $num => $messageRule) {
                $key = $ruleKey . '.' . $messageRule;
                if (! empty($messageArr[$num])) {
                    $this->setMessages($className, $key, $messageArr[$num]);
                }
            }
        }
        if (! empty($ruleArray)) {
            foreach ($ruleArray as $fieldKey => $rule) {
                $this->setRule($className, $fieldKey, $rule);
            }
            foreach ($allAnnotationArray as $annotation) {
                if (class_exists(ApiModelProperty::class) && $annotation instanceof ApiModelProperty && ! empty($annotation->value)) {
                    $this->setAttributes($className, $lastRuleKey, $annotation->value);
                }
            }
        }
    }

    protected function setRule(string $className, string $fieldName, array $rule): void
    {
        static::$content[$className]['rule'][$fieldName] = $rule;
    }

    protected function setMessages(string $className, string $key, $messages): void
    {
        static::$content[$className]['messages'][$key] = $messages;
    }

    protected function setAttributes(string $className, string $fieldName, $value): void
    {
        static::$content[$className]['attributes'][$fieldName] = $value;
    }
}
