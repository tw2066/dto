<?php

declare(strict_types=1);

namespace Hyperf\DTO\Type;

use Closure;

class ConvertCustom
{
    protected static Closure $closure;

    public static function getClosure(): Closure
    {
        return self::$closure;
    }

    public static function setClosure(Closure $closure): void
    {
        self::$closure = $closure;
    }
}
