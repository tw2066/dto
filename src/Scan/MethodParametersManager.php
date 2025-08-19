<?php

declare(strict_types=1);

namespace Hyperf\DTO\Scan;

use Hyperf\Di\ReflectionManager;
use Hyperf\DTO\Annotation\Contracts\RequestBody;
use Hyperf\DTO\Annotation\Contracts\RequestFormData;
use Hyperf\DTO\Annotation\Contracts\RequestHeader;
use Hyperf\DTO\Annotation\Contracts\RequestQuery;
use Hyperf\DTO\Annotation\Contracts\Valid;
use Hyperf\DTO\Exception\DtoException;
use ReflectionException;

class MethodParametersManager
{
    protected static array $content = [];

    public function getMethodParameter(string $className, string $methodName, string $paramName): ?MethodParameter
    {
        $key = $this->getKey($className, $methodName, $paramName);
        if (! isset(static::$content[$key])) {
            return null;
        }
        return static::$content[$key];
    }

    /**
     * 设置方法中的参数.
     * @throws ReflectionException
     */
    public function setMethodParameters(string $className, string $methodName): void
    {
        // 获取方法的反射对象
        $ref = ReflectionManager::reflectMethod($className, $methodName);
        // 获取方法上指定名称的全部注解
        $attributes = $ref->getParameters();
        $methodMark = 0;
        $headerMark = 0;
        $total = 0;
        foreach ($attributes as $attribute) {
            $methodParameters = new MethodParameter();
            $paramName = $attribute->getName();
            $mark = 0;
            if ($attribute->getAttributes(RequestQuery::class)) {
                $methodParameters->setIsRequestQuery(true);
                ++$mark;
                ++$total;
            }
            if ($attribute->getAttributes(RequestFormData::class)) {
                $methodParameters->setIsRequestFormData(true);
                ++$mark;
                ++$methodMark;
                ++$total;
            }
            if ($attribute->getAttributes(RequestBody::class)) {
                $methodParameters->setIsRequestBody(true);
                ++$mark;
                ++$methodMark;
                ++$total;
            }
            if ($attribute->getAttributes(RequestHeader::class)) {
                $methodParameters->setIsRequestHeader(true);
                ++$headerMark;
                ++$total;
            }
            if ($attribute->getAttributes(Valid::class)) {
                $methodParameters->setIsValid(true);
            }
            if ($mark > 1) {
                throw new DtoException("Parameter annotation [RequestQuery RequestFormData RequestBody] cannot exist simultaneously [{$className}::{$methodName}:{$paramName}]");
            }
            if ($headerMark > 1) {
                throw new DtoException("Parameter annotation [RequestHeader] can only exist [{$className}::{$methodName}:{$paramName}]");
            }
            if ($total > 0) {
                $this->setContent($className, $methodName, $paramName, $methodParameters);
            }
        }
        if ($methodMark > 1) {
            throw new DtoException("Method annotation [RequestFormData RequestBody] cannot exist simultaneously [{$className}::{$methodName}]");
        }
    }

    protected function setContent(string $className, string $methodName, string $paramName, MethodParameter $method): void
    {
        $key = $this->getKey($className, $methodName, $paramName);
        if (isset(static::$content[$key])) {
            return;
        }
        static::$content[$key] = $method;
    }

    protected function getKey(string $className, string $methodName, string $paramName): string
    {
        // high query efficiency
        return $className . ':' . $methodName . ':' . $paramName;
    }
}
