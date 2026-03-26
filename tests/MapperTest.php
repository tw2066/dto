<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\Mapper;
use PHPUnit\Framework\TestCase;

class MapperTest extends TestCase
{
    public function testMapSimpleObject(): void
    {
        $data = ['name' => 'test', 'age' => 25];
        $object = new TestSimpleDto();

        $result = Mapper::map($data, $object);

        $this->assertInstanceOf(TestSimpleDto::class, $result);
        $this->assertSame('test', $result->name);
        $this->assertSame(251, $result->age);
    }

    public function testMapWithNullSource(): void
    {
        $object = new TestSimpleDto();
        $result = Mapper::copyProperties(null, $object);

        $this->assertNull($result);
    }

    public function testMapArrayToObjects(): void
    {
        $data = [
            ['name' => 'test1', 'age' => 25],
            ['name' => 'test2', 'age' => 30],
        ];

        $result = Mapper::mapArray($data, TestSimpleDto::class);

        $this->assertCount(2, $result);
        $this->assertInstanceOf(TestSimpleDto::class, $result[0]);
        $this->assertSame('test1', $result[0]->name);
        $this->assertSame(25, $result[0]->age);
    }

    public function testMapArrayWithEmptyData(): void
    {
        $result = Mapper::mapArray([], TestSimpleDto::class);
        $this->assertSame([], $result);

        $result = Mapper::mapArray(null, TestSimpleDto::class);
        $this->assertSame([], $result);
    }

    public function testGetJsonMapperReturnsSameInstance(): void
    {
        $mapper1 = Mapper::getJsonMapper();
        $mapper2 = Mapper::getJsonMapper();

        $this->assertSame($mapper1, $mapper2);
    }

    public function testGetJsonMapperWithCustomKey(): void
    {
        $mapper1 = Mapper::getJsonMapper('default');
        $mapper2 = Mapper::getJsonMapper('custom');

        $this->assertNotSame($mapper1, $mapper2);
    }
}

class TestSimpleDto
{
    public string $name = '';

    public int $age = 0;
}