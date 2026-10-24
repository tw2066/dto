<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Boundary\Models;

class BaseDto
{
    /** @var Money */
    public $price;

    /** @var Money[] */
    public array $prices = [];
}
