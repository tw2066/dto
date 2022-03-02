<?php

declare(strict_types=1);

namespace Hyperf\DTO;

trait DtoCommon
{
    /**
     * Checks if the given type is a "simple type".
     *
     * @param string $type type name from gettype()
     *
     * @return bool True if it is a simple PHP type
     *
     * @see isFlatType()
     */
    protected static function isSimpleType($type)
    {
        return $type == 'string'
            || $type == 'boolean' || $type == 'bool'
            || $type == 'integer' || $type == 'int'
            || $type == 'double' || $type == 'float'
            || $type == 'array' || $type == 'object';
    }
}
