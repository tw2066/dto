<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Utils\Contracts\Arrayable;
use JsonMapper;
use JsonMapper_Exception;

class Mapper
{
    protected static JsonMapper $jsonMapper;

    public function __construct()
    {
        self::$jsonMapper = new JsonMapper();
        self::$jsonMapper->bEnforceMapType = false;
        //self::$jsonMapper->bIgnoreVisibility = true;
    }

    /**
     * @throws JsonMapper_Exception
     */
    public static function map($json, $object)
    {
        return self::$jsonMapper->map($json, $object);
    }

    /**
     * @param $obj
     * @param $toObj
     * @throws JsonMapper_Exception
     * @return null|object $toObj
     */
    public static function copy($obj, $toObj): ?object
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
     * @throws JsonMapper_Exception
     * @return array $className[]
     */
    public static function copyArray($json, $className): array
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
