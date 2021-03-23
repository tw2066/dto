<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Dependencies;

use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Contracts\Jsonable;
use JsonMapper;

class SimpleNormalizer extends \Hyperf\Utils\Serializer\SimpleNormalizer
{

    public function denormalize($data, string $class)
    {
        switch ($class) {
            case 'int':
                return (int)$data;
            case 'string':
                return (string)$data;
            case 'float':
                return (float)$data;
            case 'array':
                return (array)$data;
            case 'bool':
                return (bool)$data;
            case 'mixed':
                return $data;
            default:
                if (ApplicationContext::getContainer()->has($class)) {
                    $obj = ApplicationContext::getContainer()->get($class);
                    if($obj instanceof Jsonable){
                        $mapper = ApplicationContext::getContainer()->get(JsonMapper::class);
                        $mapper->bEnforceMapType = false;
                        return $mapper->map($data,make($class));
                    }
                }
                return $data;
        }
    }
}
