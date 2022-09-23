<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Contract\Arrayable;
use Hyperf\DTO\JsonMapperDto as JsonMapper;

class Mapper
{
    protected static ?JsonMapper $jsonMapper = null;

    protected static function getJsonMapper(): JsonMapper
    {
        if(static::$jsonMapper === null){
            static::$jsonMapper = new JsonMapper();
            //将数组传递给映射
            static::$jsonMapper->bEnforceMapType = false;
            //私有属性和函数
            static::$jsonMapper->bIgnoreVisibility = true;
        }
        return static::$jsonMapper;
    }

    public static function map($json, object $object)
    {
        return static::getJsonMapper()->map($json, $object);
    }

    public static function copyProperties($source, object $target)
    {
        if ($source == null) {
            return null;
        }
        if ($source instanceof Arrayable) {
            return static::getJsonMapper()->map($source->toArray(), $target);
        }
        return static::getJsonMapper()->map($source, $target);
    }

    public static function mapArray($json, string $className)
    {
        if (empty($json)) {
            return [];
        }
        if ($json instanceof Arrayable) {
            return static::getJsonMapper()->mapArray($json->toArray(), [], $className);
        }
        return static::getJsonMapper()->mapArray($json, [], $className);
    }
}
