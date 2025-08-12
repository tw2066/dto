<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

use Hyperf\ApiDocs\Annotation\ApiModelProperty;
use Hyperf\Di\Annotation\MultipleAnnotation;
use Hyperf\DTO\Annotation\JSONField;
use Hyperf\DTO\Annotation\Validation\BaseValidation;
use Hyperf\DTO\ApiAnnotation;
use Hyperf\Stringable\Str;

class ValidationManager
{
    protected static array $content = [];

    public function getData(string $className): array
    {
        return static::$content[$className] ?? [];
    }

    /**
     * 生成验证数据.
     */
    public function generateValidation(string $className, string $fieldName): void
    {
        /** @var BaseValidation[] $validation */
        $validationArr = [];
        $allAnnotationArray = ApiAnnotation::getClassProperty($className, $fieldName);
        $aliasName = null;
        foreach ($allAnnotationArray as $multipleAnnotation) {
            if ($multipleAnnotation instanceof JSONField){
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
        foreach ($validationArr as $validation) {
            if (empty($validation->getRule())) {
                continue;
            }
            // 支持自定义key eg: required|date|after:start_date
            $customKey = $validation->getCustomKey() ?: ($aliasName ?? $fieldName);
            $rule = $validation->getRule();
            if (is_string($rule) && Str::contains($rule, '|')) {
                $ruleArr = explode('|', $rule);
                foreach ($ruleArr as $item) {
                    $ruleArray[$customKey][] = $item;
                }
            } else {
                $ruleArray[$customKey][] = $rule;
            }

            if (empty($validation->messages)) {
                continue;
            }
            [$messagesRule] = explode(':', (string) $validation->getRule());
            $key = $customKey . '.' . $messagesRule;
            $this->setMessages($className, $key, $validation->messages);
        }
        if (! empty($ruleArray)) {
            foreach ($ruleArray as $fieldKey => $rule) {
                $this->setRule($className, $fieldKey, $rule);
            }
            foreach ($allAnnotationArray as $annotation) {
                if (class_exists(ApiModelProperty::class) && $annotation instanceof ApiModelProperty && ! empty($annotation->value)) {
                    $this->setAttributes($className, $fieldName, $annotation->value);
                }
            }
        }
    }

    protected function setRule(string $className, string $fieldName, $rule): void
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
