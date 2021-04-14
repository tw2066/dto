<?php

namespace Hyperf\DTO;

use Hyperf\Database\Model\Model;
use JsonMapper;

class Mapper
{
    protected static JsonMapper $jsonMapper;

    public function __construct()
    {
        self::$jsonMapper = new JsonMapper();
        self::$jsonMapper->bEnforceMapType = false;
    }

    public function map($json, $object)
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
        if ($obj instanceof Model) {
            return self::$jsonMapper->map($obj->toArray(), $toObj);
        } else {
            return self::$jsonMapper->map($obj, $toObj);
        }
    }
}