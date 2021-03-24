<?php

namespace Tang\DTO;

use Hyperf\Utils\Contracts\Jsonable;

abstract class Response implements Jsonable
{
    public function __toString(): string
    {
        return json_encode($this,JSON_UNESCAPED_UNICODE);
    }
}