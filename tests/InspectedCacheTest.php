<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\JsonMapper;
use Hyperf\DTO\Mapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionProperty;

/**
 * 回归：客户端可控键的进程级 JsonMapper 缓存必须有界。
 *
 * 已知和未知键共用固定容量缓存，未知键命中负缓存后不再重复反射。
 * 负缓存满后停止写入新未知键，避免唯一键造成缓存抖动。
 * 单次 map 最多处理 1024 个输入键，超出立即失败，避免线性 CPU 放大。
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

    public function testUnknownKeysCacheIsBounded(): void
    {
        $data = [];
        for ($i = 0; $i < 1024; ++$i) {
            $data['unknown_key_' . $i] = 1;
        }

        Mapper::map($data, new CacheProbeDto());

        $cache = $this->inspectedCache();
        $this->assertCount(256, $cache[CacheProbeDto::class]);
        $this->assertArrayHasKey('unknown_key_0', $cache[CacheProbeDto::class]);
        $this->assertArrayNotHasKey('unknown_key_1023', $cache[CacheProbeDto::class]);
    }

    public function testDirectJsonMapperCallEnforcesKeyLimit(): void
    {
        $data = [];
        for ($i = 0; $i < 1025; ++$i) {
            $data['k' . $i] = 1;
        }

        $mapper = new CountingJsonMapper();
        $mapper->bEnforceMapType = false;
        try {
            $mapper->map($data, new CacheProbeDto());
            $this->fail('Expected JsonMapper_Exception was not thrown.');
        } catch (\JsonMapper_Exception $e) {
            $this->assertStringContainsString('maximum is 1024', $e->getMessage());
        }
        $this->assertSame(1024, $mapper->inspectionCount);
    }

    public function testKnownVariantKeysCacheIsBounded(): void
    {
        $data = [];
        for ($i = 1; $i <= 256; ++$i) {
            $data[str_repeat(' ', $i) . 'name'] = 'x';
        }

        $dto = Mapper::map($data, new CacheVariantDto());

        $this->assertSame('x', $dto->getName());
        $this->assertCount(256, $this->inspectedCache()[CacheVariantDto::class]);
    }

    public function testKnownVariantKeysAlsoHaveInputLimit(): void
    {
        $data = [];
        for ($i = 1; $i <= 1025; ++$i) {
            $data[str_repeat(' ', $i) . 'name'] = 'x';
        }

        $this->expectException(\JsonMapper_Exception::class);
        Mapper::map($data, new CacheVariantDto());
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
        $cache = $this->inspectedCache()[CacheProbeDto::class];
        $this->assertTrue($cache['name'][0]);
        $this->assertFalse($cache['unknown_a'][0]);
        $this->assertFalse($cache['unknown_b'][0]);
    }

    public function testMapArrayUsesBoundedNegativeCache(): void
    {
        $dtos = Mapper::mapArray(
            [['name' => 'a', 'unknown' => 1], ['name' => 'b', 'other_unknown' => 2]],
            CacheProbeDto::class
        );

        $this->assertSame('a', $dtos[0]->name);
        $this->assertSame('b', $dtos[1]->name);
        $cache = $this->inspectedCache()[CacheProbeDto::class];
        $this->assertSame(['name', 'unknown', 'other_unknown'], array_keys($cache));
    }

    public function testRepeatedUnknownKeysDoNotGrowCache(): void
    {
        $mapper = Mapper::getJsonMapper();
        for ($round = 0; $round < 5; ++$round) {
            $dto = $mapper->map(['name' => 'x', 'round_' . $round . '_key' => 1], new CacheProbeDto());
            $this->assertSame('x', $dto->name);
            $cache = $this->inspectedCache()[CacheProbeDto::class];
            $this->assertTrue($cache['name'][0]);
            $this->assertFalse($cache['round_' . $round . '_key'][0]);
            $this->assertLessThanOrEqual(256, count($cache));
        }
    }

    public function testRepeatedUnknownKeyUsesNegativeCache(): void
    {
        $mapper = new CountingJsonMapper();
        $mapper->bEnforceMapType = false;
        $mapper->map(['unknown' => 1], new CacheProbeDto());
        $mapper->map(['unknown' => 1], new CacheProbeDto());

        $this->assertSame(1, $mapper->inspectionCount);
    }

    public function testUndefinedPropertyLogsAreLimited(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(32))->method('log');
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $mapper->setLogger($logger);
        $data = [];
        for ($i = 0; $i < 100; ++$i) {
            $data['unknown_' . $i] = 1;
        }

        $mapper->map($data, new CacheProbeDto());
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

class CacheVariantDto
{
    private string $name = '';

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

class CountingJsonMapper extends JsonMapper
{
    public int $inspectionCount = 0;

    protected function inspectProperty(ReflectionClass $rc, string $name, bool $allowPropertyCaseFallback = false): array
    {
        ++$this->inspectionCount;
        return parent::inspectProperty($rc, $name, $allowPropertyCaseFallback);
    }
}
