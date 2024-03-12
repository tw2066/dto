<?php

declare(strict_types=1);

namespace Hyperf\DTO\Event;

/**
 * 主要用于自动化测试时,手动触发DTO
 */
class BeforeDtoStart
{
    public function __construct(public string $name = 'http') {}
}
