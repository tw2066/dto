<?php

declare(strict_types=1);

namespace HyperfTest\DTO\JsonMapper;

use Hyperf\DTO\JsonMapper;
use PHPUnit\Framework\TestCase;

/**
 * 改编自 netresearch/jsonmapper（OSL-3.0）的 tests/Enums_PHP81_Test.php。
 *
 * simpleMap() 通过 BackedEnum::from() 映射枚举，严格对象类型检查不会拦截枚举；
 * 枚举数组走常规 Class[] 元素映射。
 *
 * @internal
 * @coversNothing
 */
class JsonMapperEnumMappingTest extends TestCase
{
    public function testBackedEnumsAreMapped(): void
    {
        $dto = $this->mapper()->simpleMap([
            'stringBackedEnum' => 'foo',
            'intBackedEnum' => 2,
        ], new UpstreamEnumDto());

        $this->assertSame(UpstreamStringBackedEnum::FOO, $dto->stringBackedEnum);
        $this->assertSame(UpstreamIntBackedEnum::BAR, $dto->intBackedEnum);
    }

    public function testNullableBackedEnumAcceptsNull(): void
    {
        $dto = $this->mapper()->simpleMap([
            'nullableStringEnum' => null,
        ], new UpstreamEnumDto());

        $this->assertNull($dto->nullableStringEnum);
    }

    public function testArrayOfBackedEnums(): void
    {
        $dto = $this->mapper()->simpleMap([
            'stringBackedEnums' => ['foo', 'bar'],
            'intBackedEnums' => [1, 2],
        ], new UpstreamEnumDto());

        $this->assertSame(
            [UpstreamStringBackedEnum::FOO, UpstreamStringBackedEnum::BAR],
            $dto->stringBackedEnums
        );
        $this->assertSame(
            [UpstreamIntBackedEnum::FOO, UpstreamIntBackedEnum::BAR],
            $dto->intBackedEnums
        );
    }

    public function testBackedEnumsWorkUnderStrictObjectTypeChecking(): void
    {
        $mapper = $this->mapper();
        $mapper->bStrictObjectTypeChecking = true;

        $dto = $mapper->simpleMap([
            'stringBackedEnum' => 'foo',
            'intBackedEnum' => 2,
        ], new UpstreamEnumDto());

        $this->assertSame(UpstreamStringBackedEnum::FOO, $dto->stringBackedEnum);
        $this->assertSame(UpstreamIntBackedEnum::BAR, $dto->intBackedEnum);
    }

    private function mapper(): JsonMapper
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $mapper->bStrictNullTypes = false;
        return $mapper;
    }
}

enum UpstreamStringBackedEnum: string
{
    case FOO = 'foo';
    case BAR = 'bar';
}

enum UpstreamIntBackedEnum: int
{
    case FOO = 1;
    case BAR = 2;
}

class UpstreamEnumDto
{
    public UpstreamStringBackedEnum $stringBackedEnum;

    public UpstreamIntBackedEnum $intBackedEnum;

    public ?UpstreamStringBackedEnum $nullableStringEnum = null;

    /** @var UpstreamStringBackedEnum[] */
    public array $stringBackedEnums = [];

    /** @var UpstreamIntBackedEnum[] */
    public array $intBackedEnums = [];
}
