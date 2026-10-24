<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\JsonMapper;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class JsonMapperIntKeyTest extends TestCase
{
    public function testIntegerJsonKeysAreHandledWithoutTypeError(): void
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $mapper->bStrictNullTypes = false;

        $dto = $mapper->map([1, 2], new IntKeyDto());
        $this->assertSame('', $dto->name);

        $holder = $mapper->map(['items' => [[1, 2]]], new IntKeyItemArrayDto());
        $this->assertInstanceOf(IntKeyItem::class, $holder->items[0]);
        $this->assertSame(0, $holder->items[0]->id);
    }
}

class IntKeyDto
{
    public string $name = '';
}

class IntKeyItem
{
    public int $id = 0;
}

class IntKeyItemArrayDto
{
    /** @var IntKeyItem[] */
    public array $items = [];
}
