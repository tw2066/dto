<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\DTO\JsonMapperDto as JsonMapper;
use Hyperf\Utils\Contracts\Arrayable;

class Mapper
{
    protected static JsonMapper $jsonMapper;

    public function __construct()
    {
        self::$jsonMapper = new JsonMapper();
        self::$jsonMapper->bEnforceMapType = false;
    }

    public static function map($json, object $object)
    {
        return self::$jsonMapper->map($json, $object);
    }

    public static function copyProperties($source, object $target)
    {
        if ($source == null) {
            return null;
        }
        if ($source instanceof Arrayable) {
            return self::$jsonMapper->map($source->toArray(), $target);
        }
        return self::$jsonMapper->map($source, $target);
    }

    public static function mapArray($json, string $className)
    {
        if (empty($json)) {
            return [];
        }
        if ($json instanceof Arrayable) {
            return self::$jsonMapper->mapArray($json->toArray(), [], $className);
        }
        return self::$jsonMapper->mapArray($json, [], $className);
    }
}
