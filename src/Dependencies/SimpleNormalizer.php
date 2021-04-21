<?php

declare(strict_types=1);

namespace Hyperf\DTO\Dependencies;

use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Contracts\Jsonable;
use Hyperf\DTO\Mapper;

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
                if (Mapper::isMap($class)) {
                    return Mapper::map($data, make($class));
                }
                return $data;
        }
    }
}
