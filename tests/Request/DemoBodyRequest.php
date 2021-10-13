<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Request;

class DemoBodyRequest
{
    public const IN = ['A', 'B', 'C'];

    private int $int = 5;

    private string $string = 'string';

    /**
     * @var \HyperfTest\DTO\Request\Address[]
     */
    private array $arr;

    private object $obj;
}
