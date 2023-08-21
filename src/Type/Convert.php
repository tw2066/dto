<?php

declare(strict_types=1);

namespace Hyperf\DTO\Type;

use Hyperf\Stringable\Str;

enum Convert: string
{
    /*
     * 驼峰
     */
    case CAMEL = 'camel';
    case STUDLY = 'studly';

    /*
     * 下划线
     */
    case SNAKE = 'snake';
    case NONE = 'none';
//    case CUSTOM = 'custom';

    public function getValue(string $data): string
    {
        return match ($this) {
            Convert::CAMEL => Str::camel($data),
            Convert::STUDLY => Str::studly($data),
            Convert::SNAKE => Str::snake($data),
            Convert::NONE => $data,
        };
    }
}
