<?php

declare(strict_types=1);

namespace Hyperf\DTO\Mapping;

/**
 * @internal 仅存在于一次公开映射/解析调用期间，不能在 worker 间共享
 */
class MappingContext
{
    public const MAX_NODE_KEYS = 1024;

    public const MAX_TOTAL_KEYS = 65536;

    public const MAX_DEPTH = 100;

    public const MAX_UNDEFINED_PROPERTY_LOGS = 32;

    public int $totalKeys = 0;

    public int $undefinedPropertyLogs = 0;

    public function enterNode(int $keyCount, string $className, int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw new \JsonMapper_Exception('DTO mapping nesting too deep (max ' . self::MAX_DEPTH . '): ' . $className);
        }
        if ($keyCount > self::MAX_NODE_KEYS) {
            throw new \JsonMapper_Exception(
                'Too many JSON properties for object of type ' . $className
                . '; maximum is ' . self::MAX_NODE_KEYS
            );
        }
        $this->totalKeys += $keyCount;
        if ($this->totalKeys > self::MAX_TOTAL_KEYS) {
            throw new \JsonMapper_Exception('Too many JSON properties in mapping tree; maximum is ' . self::MAX_TOTAL_KEYS);
        }
    }
}
