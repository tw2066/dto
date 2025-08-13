<?php

declare(strict_types=1);

namespace Hyperf\DTO\Event;

/**
 * It is mainly used for manually triggering the DTO during automated testing
 */
class BeforeDtoStart
{
    public function __construct(public string $name = 'http') {}
}
