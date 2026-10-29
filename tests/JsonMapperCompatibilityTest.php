<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\JsonMapper;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class JsonMapperCompatibilityTest extends TestCase
{
    public function testUpstreamMapEntryIsDisabled(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('use simpleMap()');
        $this->mapper()->map(['id' => 7], new CompatibilityAcronymDto());
    }

    public function testAcronymSetterOnlyAcceptsPrimaryKey(): void
    {
        $mapper = $this->mapper();
        $this->assertSame(7, $mapper->simpleMap(['id' => 7], new CompatibilityAcronymDto())->getId());
        $this->assertSame(0, $mapper->simpleMap(['ID' => 8], new CompatibilityAcronymDto())->getId());
        $this->assertSame(0, $mapper->simpleMap(['Id' => 9], new CompatibilityAcronymDto())->getId());
    }

    public function testOnlyPrimaryHeaderKeyIsAccepted(): void
    {
        $mapper = $this->mapper();
        $dto = $mapper->simpleMap(['xRequestId' => 'accepted'], new CompatibilityHeaderDto());
        $this->assertSame('accepted', $dto->xRequestId);

        foreach (['XRequestId', 'x-request-id', 'X-Request-Id'] as $key) {
            $dto = $mapper->simpleMap([$key => 'ignored'], new CompatibilityHeaderDto());
            $this->assertSame('', $dto->xRequestId);
        }
    }

    public function testInvalidUnionValuesThrowJsonMapperExceptionInsteadOfTypeError(): void
    {
        $dto = $this->mapper()->simpleMap(['value' => 5], new CompatibilityUnionPropertyDto());
        $this->assertSame(5, $dto->value);

        foreach ([new CompatibilityUnionPropertyDto(), new CompatibilityUnionSetterDto()] as $dto) {
            try {
                $this->mapper()->simpleMap(['value' => ['x']], $dto);
                $this->fail('Expected JsonMapper_Exception was not thrown.');
            } catch (\JsonMapper_Exception $e) {
                $this->assertInstanceOf(\TypeError::class, $e->getPrevious());
            }
        }
    }

    public function testNullArrayElementsUseLibraryExceptionAtVariadicBoundary(): void
    {
        $mapper = $this->mapper();
        $mapper->bStrictNullTypesInArrays = false;

        try {
            $mapper->simpleMap(['children' => [['name' => 'a'], null]], new CompatibilityVariadicParentDto());
            $this->fail('Expected JsonMapper_Exception was not thrown.');
        } catch (\JsonMapper_Exception $e) {
            $this->assertInstanceOf(\TypeError::class, $e->getPrevious());
        }

        $result = $mapper->mapArray([['name' => 'a'], null], [], CompatibilityVariadicChildDto::class);
        $this->assertInstanceOf(CompatibilityVariadicChildDto::class, $result[0]);
        $this->assertNull($result[1]);
    }

    public function testVariadicSetterAcceptsSingleScalar(): void
    {
        $dto = $this->mapper()->simpleMap(['ids' => 5], new CompatibilityIntegerVariadicDto());

        $this->assertSame([5], $dto->ids);
    }

    public function testNullInputMapsToDefaultObject(): void
    {
        $dto = $this->mapper()->simpleMap(null, new CompatibilityHeaderDto());

        $this->assertSame('', $dto->xRequestId);
    }

    private function mapper(): JsonMapper
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $mapper->bStrictNullTypes = false;
        return $mapper;
    }
}

class CompatibilityAcronymDto
{
    private int $id = 0;

    public function setID(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }
}

class CompatibilityHeaderDto
{
    public string $xRequestId = '';
}

class CompatibilityUnionPropertyDto
{
    public int|string|null $value = null;
}

class CompatibilityUnionSetterDto
{
    private int|string $value = 0;

    public function setValue(int|string $value): void
    {
        $this->value = $value;
    }
}

class CompatibilityVariadicChildDto
{
    public string $name = '';
}

class CompatibilityVariadicParentDto
{
    public array $children = [];

    public function setChildren(CompatibilityVariadicChildDto ...$children): void
    {
        $this->children = $children;
    }
}

class CompatibilityIntegerVariadicDto
{
    public array $ids = [];

    public function setIds(int ...$ids): void
    {
        $this->ids = $ids;
    }
}
