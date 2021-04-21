<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Database\Model\Model;
use Hyperf\DTO\Contracts\DTO;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Collection;
use Hyperf\Utils\Contracts\Jsonable;
use JsonMapper;
use Throwable;

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

    public static function isMap($obj)
    {
        if (!is_object($obj) && ApplicationContext::getContainer()->has($obj)) {
            try {
                $obj = ApplicationContext::getContainer()->get($obj);
            } catch (Throwable $throwable) {
                return false;
            }
        }

        if ($obj instanceof DTO
            || $obj instanceof Jsonable
        ) {
            return true;
        }
        return false;
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
        if ($json instanceof Collection) {
            return self::$jsonMapper->mapArray($json->toArray(), array(), $className);
        }
        return self::$jsonMapper->mapArray($json, array(), $className);
    }
}