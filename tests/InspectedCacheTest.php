<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\Mapper;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * 回归：客户端可控的未知键不得进入进程级 JsonMapper 缓存。
 *
 * 修复方式：JsonMapper 覆写父类 map()，inspectProperty() 未命中的
 * 检查结果不写入 arInspectedClasses，未知键从源头不进入缓存。
 *
 * @internal
 * @coversNothing
 */
class InspectedCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearCache();
    }

    protected function tearDown(): void
    {
        $this->clearCache();
        parent::tearDown();
    }

    public function testUnknownKeysAreNeverCached(): void
    {
        $data = [];
        for ($i = 0; $i < 2000; ++$i) {
            $data['unknown_key_' . $i] = 1;
        }

        Mapper::map($data, new CacheProbeDto());

        $this->assertSame([], $this->inspectedCache());
    }

    public function testDirectJsonMapperCallIsProtected(): void
    {
        $data = [];
        for ($i = 0; $i < 10000; ++$i) {
            $data['k' . $i] = 1;
        }

        // 直接调用 JsonMapper::map（不经过 Mapper::map）：防线内建于
        // 类自身，而不是依赖调用方在映射后剪枝。
        Mapper::getJsonMapper()->map($data, new CacheProbeDto());

        $this->assertSame([], $this->inspectedCache());
    }

    public function testKnownPropertiesAreCached(): void
    {
        $mapper = Mapper::getJsonMapper();
        $mapper->map(['name' => 'ok'], new CacheProbeDto());

        $cache = $this->inspectedCache();
        $this->assertArrayHasKey(CacheProbeDto::class, $cache);
        $this->assertSame(['name'], array_keys($cache[CacheProbeDto::class]));
        // 命中的检查结果照常缓存，避免重复反射与注解解析
        $this->assertTrue($cache[CacheProbeDto::class]['name'][0]);
    }

    public function testMixedKnownAndUnknownKeys(): void
    {
        $dto = Mapper::map(['name' => 'ok', 'unknown_a' => 1, 'unknown_b' => 2], new CacheProbeDto());

        $this->assertSame('ok', $dto->name);
        $cache = $this->inspectedCache();
        $this->assertSame(['name'], array_keys($cache[CacheProbeDto::class] ?? []));
    }

    public function testMapArrayDoesNotCacheUnknownKeys(): void
    {
        $dtos = Mapper::mapArray(
            [['name' => 'a', 'unknown' => 1], ['name' => 'b', 'other_unknown' => 2]],
            CacheProbeDto::class
        );

        $this->assertSame('a', $dtos[0]->name);
        $this->assertSame('b', $dtos[1]->name);
        $cache = $this->inspectedCache();
        $this->assertSame(['name'], array_keys($cache[CacheProbeDto::class] ?? []));
    }

    public function testRepeatedUnknownKeysDoNotGrowCache(): void
    {
        $mapper = Mapper::getJsonMapper();
        for ($round = 0; $round < 5; ++$round) {
            $dto = $mapper->map(['name' => 'x', 'round_' . $round . '_key' => 1], new CacheProbeDto());
            $this->assertSame('x', $dto->name);
            $cache = $this->inspectedCache();
            $this->assertSame(['name'], array_keys($cache[CacheProbeDto::class] ?? []));
        }
    }

    private function clearCache(): void
    {
        $property = new ReflectionProperty(\JsonMapper::class, 'arInspectedClasses');
        $property->setAccessible(true);
        $property->setValue(Mapper::getJsonMapper(), []);
    }

    private function inspectedCache(): array
    {
        $property = new ReflectionProperty(\JsonMapper::class, 'arInspectedClasses');
        $property->setAccessible(true);
        return $property->getValue(Mapper::getJsonMapper());
    }
}

class CacheProbeDto
{
    public string $name = '';
}
