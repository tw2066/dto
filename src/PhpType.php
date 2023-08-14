<?php

declare(strict_types=1);

namespace Hyperf\DTO;

enum PhpType: string
{
    case BOOL = 'bool';
    case FLOAT = 'float';
    case STRING = 'string';
    case ARRAY = 'array';
    case OBJECT = 'object';
    case INT = 'int';
}
