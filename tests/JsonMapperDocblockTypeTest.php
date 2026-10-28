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

    public function testUnsupportedUnionDocblockFallsBackToDeclaredArrayType(): void
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;

        $dto = $mapper->map(['items' => [['city' => 'A']]], new DocblockUnsupportedUnionDto());

        // 不支持的联合类型被忽略，回退到反射类型 array，子项按原值保留
        $this->assertSame([['city' => 'A']], $dto->items);
    }

    public function testUnsupportedUnionDocblockDoesNotBreakUnrelatedFields(): void
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;

        $dto = $mapper->map(['name' => 'alice'], new DocblockUnionHolderDto());
        $this->assertSame('alice', $dto->name);

        // 第一次调用不再抛错，后续映射仍保持完整元数据
        $dto = $mapper->map(['name' => 'bob'], new DocblockUnionHolderDto());
        $this->assertSame('bob', $dto->name);
    }

    public function testUnsupportedUnionDocblockFallsBackToRawValue(): void
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;

        $dto = $mapper->map(['value' => 'hello'], new DocblockUnionHolderDto());

        // 联合类型无法确定映射目标，按原值写入
        $this->assertSame('hello', $dto->value);
    }

    public function testThirdPartyUnionDocblockClassCanBeMappedByKnownKeys(): void
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;

        $server = $mapper->map(['url' => 'http://localhost'], new DocblockThirdPartyServer());
        $this->assertSame('http://localhost', $server->url);

        // 启动扫描同样不应抛错，且字段元数据完整
        $propertyManager = new PropertyManager(new DtoCommon(), new PropertyEnum());
        $properties = $propertyManager->getPropertyByClass(DocblockThirdPartyServer::class);
        $this->assertArrayHasKey('url', $properties);
        $this->assertArrayHasKey('enum', $properties);
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

class DocblockUnionHolderDto
{
    public string $name = '';

    /** @var int|string|null */
    public int|string|null $value = null;
}

class DocblockThirdPartyBase
{
    /** @var list<string|int|float|bool|\UnitEnum>|class-string */
    public array $enum = [];
}

class DocblockThirdPartyServer extends DocblockThirdPartyBase
{
    public string $url = '';
}
