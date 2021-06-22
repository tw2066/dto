<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Utils\Contracts\Arrayable;
use JsonMapper;

class Mapper
{
    protected static JsonMapper $jsonMapper;

    public function __construct()
    {
        self::$jsonMapper = new JsonMapper();
        self::$jsonMapper->bEnforceMapType = false;
    }

    public static function map($json, $object)
    {
        return self::$jsonMapper->map($json, $object);
    }

    /**
     * @param $obj
     * @param $toObj
     * @return object $toObj
     */
    public static function copy($obj, $toObj)
    {
        if ($obj == null) {
            return null;
        }
        if ($obj instanceof Arrayable) {
            return self::$jsonMapper->map($obj->toArray(), $toObj);
        }
        return self::$jsonMapper->map($obj, $toObj);
    }

    /**
     * @param $json
     * @param $className
     * @return array $className[]
     */
    public static function copyArray($json, $className)
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
