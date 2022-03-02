<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Request;

class DemoBodyRequest
{
    public const IN = ['A', 'B', 'C'];

    private int $int = 5;

    private string $string = 'string';

    /**
     * @var Address[]
     */
    private array $arrClass;

    /**
     * @var int[]
     */
    private array $arrInt;

    private object $obj;
}
