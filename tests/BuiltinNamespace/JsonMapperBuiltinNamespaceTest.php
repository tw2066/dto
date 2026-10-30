<?php

declare(strict_types=1);

namespace HyperfTest\DTO\BuiltinNamespace;

use Hyperf\DTO\JsonMapper;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class JsonMapperBuiltinNamespaceTest extends TestCase
{
    public function testBuiltinAliasCanResolveSameNamespaceClass(): void
    {
        $propertyDto = $this->mapper()->simpleMap(['items' => [['name' => 'property']]], new ResourcePropertyDto());
        $setterDto = $this->mapper()->simpleMap(['items' => [['name' => 'setter']]], new ResourceSetterDto());

        $this->assertInstanceOf(Resource::class, $propertyDto->items[0]);
        $this->assertSame('property', $propertyDto->items[0]->name);
        $this->assertInstanceOf(Resource::class, $setterDto->getItems()[0]);
        $this->assertSame('setter', $setterDto->getItems()[0]->name);
    }

    private function mapper(): JsonMapper
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $mapper->bStrictNullTypes = false;
        return $mapper;
    }
}

class Resource
{
    public string $name = '';
}

class ResourcePropertyDto
{
    /** @var Resource[] */
    public array $items = [];
}

class ResourceSetterDto
{
    private array $items = [];

    /** @param Resource[] $items */
    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function getItems(): array
    {
        return $this->items;
    }
}
