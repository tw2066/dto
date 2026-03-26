<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\Mapper;
use PHPUnit\Framework\TestCase;

class DataConversionBoundaryTest extends TestCase
{
    public function testEmptyArrayMapping(): void
    {
        $data = [];
        $dto = new BoundarySimpleDto();

        $result = Mapper::map($data, $dto);

        $this->assertInstanceOf(BoundarySimpleDto::class, $result);
        $this->assertSame('', $result->name);
        $this->assertNull($result->age);
    }

    public function testPartialDataMapping(): void
    {
        $data = ['name' => 'Partial'];
        $dto = new BoundarySimpleDto();

        $result = Mapper::map($data, $dto);

        $this->assertSame('Partial', $result->name);
        $this->assertNull($result->age);
    }

    public function testExtraDataMapping(): void
    {
        $data = ['name' => 'Extra', 'age' => 30, 'extraField' => 'ignored'];
        $dto = new BoundarySimpleDto();

        $result = Mapper::map($data, $dto);

        $this->assertSame('Extra', $result->name);
        $this->assertSame(30, $result->age);
    }

    public function testNullValueMapping(): void
    {
        $data = ['age' => null];
        $dto = new BoundarySimpleDto();

        $result = Mapper::map($data, $dto);

        $this->assertSame('', $result->name);
        $this->assertNull($result->age);
    }

    public function testZeroValueMapping(): void
    {
        $data = ['name' => '', 'age' => 0];
        $dto = new BoundarySimpleDto();

        $result = Mapper::map($data, $dto);

        $this->assertSame('', $result->name);
        $this->assertSame(0, $result->age);
    }

    public function testBooleanFalseMapping(): void
    {
        $data = ['enabled' => false];
        $dto = new BoundaryBooleanDto();

        $result = Mapper::map($data, $dto);

        $this->assertFalse($result->enabled);
    }

    public function testMapArrayWithValidElements(): void
    {
        $data = [
            ['name' => 'valid', 'value' => 1],
            ['name' => 'another', 'value' => 2],
        ];

        $result = Mapper::mapArray($data, BoundaryItemDto::class);

        $this->assertCount(2, $result);
        $this->assertSame('valid', $result[0]->name);
        $this->assertSame('another', $result[1]->name);
    }
}

class BoundarySimpleDto
{
    public string $name = '';
    public ?int $age = null;
}

class BoundaryBooleanDto
{
    public bool $enabled = false;
}

class BoundaryItemDto
{
    public string $name = '';
    public int $value = 0;
}