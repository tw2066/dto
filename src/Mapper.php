<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\Arrayable;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DTO\Mapping\MappingContext;
use Hyperf\DTO\Mapping\ResolvedInput;

/**
 * Helper class for mapping data to DTO objects.
 */
class Mapper
{
    protected static array $jsonMapper = [];

    /**
     * Map data to an object.
     *
     * @param mixed $json The data to map
     * @param object $object The target object
     * @return object The populated object
     */
    public static function map(mixed $json, object $object): object
    {
        return static::getJsonMapper()->map($json, $object);
    }

    /**
     * @internal DTO 数组共享同一个请求级映射预算。
     */
    public static function mapWithContext(mixed $json, object $object, MappingContext $context): object
    {
        return static::getJsonMapper()->mapWithContext($json, $object, $context);
    }

    /**
     * @internal 仅用于验证或 RPC DTO 映射前的一次输入解析。
     */
    public static function resolveInput(mixed $json, string $className, ?MappingContext $context = null): ResolvedInput
    {
        return static::getJsonMapper()->resolveInput($json, $className, $context);
    }

    /**
     * @internal 直接消费 resolveInput() 的同一结果。
     */
    public static function mapResolvedInput(ResolvedInput $input, object $object): object
    {
        return static::getJsonMapper()->mapResolvedInput($input, $object);
    }

    /**
     * Copy properties from source to target object.
     *
     * @param mixed $source The source data
     * @param object $target The target object
     * @return null|object The populated object or null if source is null
     */
    public static function copyProperties(mixed $source, object $target): ?object
    {
        if ($source == null) {
            return null;
        }
        if ($source instanceof Arrayable) {
            return static::getJsonMapper()->map($source->toArray(), $target);
        }
        return static::getJsonMapper()->map($source, $target);
    }

    /**
     * Map array data to an array of objects.
     *
     * @param mixed $json The data to map
     * @param string $className The class name to instantiate
     * @return array Array of mapped objects
     */
    public static function mapArray(mixed $json, string $className): array
    {
        if (empty($json)) {
            return [];
        }
        if ($json instanceof Arrayable) {
            return static::getJsonMapper()->mapArray($json->toArray(), [], $className);
        }
        return static::getJsonMapper()->mapArray($json, [], $className);
    }

    /**
     * Get or create a JsonMapper instance.
     *
     * @param string $key The mapper cache key
     * @return JsonMapper The JsonMapper instance
     */
    public static function getJsonMapper(string $key = 'default'): JsonMapper
    {
        if (! isset(static::$jsonMapper[$key])) {
            $jsonMapper = new JsonMapper();
            $logger = null;
            if (ApplicationContext::hasContainer()) {
                $container = ApplicationContext::getContainer();
                if ($container->has(StdoutLoggerInterface::class)) {
                    $logger = $container->get(StdoutLoggerInterface::class);
                }
            }
            $jsonMapper->setLogger($logger);
            // Allow passing arrays to mapping
            $jsonMapper->bEnforceMapType = false;
            $jsonMapper->bStrictNullTypes = false;
            $jsonMapper->bStrictNullTypesInArrays = false;
            static::$jsonMapper[$key] = $jsonMapper;
        }
        return static::$jsonMapper[$key];
    }
}
