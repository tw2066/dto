<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Utils\Contracts\Jsonable;

abstract class Response implements Jsonable
{
    public function __toString(): string
    {
        return json_encode($this, JSON_UNESCAPED_UNICODE);
    }
}
