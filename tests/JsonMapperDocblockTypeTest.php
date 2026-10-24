<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\DtoCommon;
use Hyperf\DTO\JsonMapper;
use Hyperf\DTO\Scan\PropertyEnum;
use Hyperf\DTO\Scan\PropertyManager;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class JsonMapperDocblockTypeTest extends TestCase
{
    public function testNullableAndGenericClassArraysAreMappedAndScanned(): void
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $mapper->bStrictNullTypes = false;
        $dto = $mapper->map([
            'x' => [['city' => 'A']],
            'y' => [['city' => 'B']],
            'z' => [['city' => 'C']],
            'w' => [['city' => 'D']],
        ], new DocblockTypeHolder());

        foreach (['x' => 'A', 'y' => 'B', 'z' => 'C', 'w' => 'D'] as $field => $city) {
            $this->assertInstanceOf(DocblockTypeItem::class, $dto->{$field}[0]);
            $this->assertSame($city, $dto->{$field}[0]->city);
        }

        $propertyManager = new PropertyManager(new DtoCommon(), new PropertyEnum());
        foreach (['x', 'y', 'z', 'w'] as $field) {
            $property = $propertyManager->getProperty(DocblockTypeHolder::class, $field);
            $this->assertSame(DocblockTypeItem::class, $property->arrClassName);
            $this->assertTrue($property->isClassArray());
        }
    }

    public function testUnsupportedUnionDocblockFailsClosed(): void
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $this->expectException(\JsonMapper_Exception::class);
        $mapper->map(['items' => []], new DocblockUnsupportedUnionDto());
    }
}

class DocblockTypeItem
{
    public string $city = '';
}

class DocblockTypeHolder
{
    /** @var null|DocblockTypeItem[] */
    public array $x = [];

    /** @var list<DocblockTypeItem> */
    public array $y = [];

    /** @var array<DocblockTypeItem> */
    public array $z = [];

    /** @var array<string, DocblockTypeItem> */
    public array $w = [];
}

class DocblockUnsupportedUnionDto
{
    /** @var DocblockTypeItem[]|int[] */
    public array $items = [];
}
