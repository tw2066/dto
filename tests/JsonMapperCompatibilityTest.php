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
        // 无别名时属性名即 primaryKey：连字符/大小写变体被忽略是 4.0 精确匹配的预期行为。
        // 需要映射真实 Header 键（x-request-id）时，用 #[JSONField('x-request-id')] 显式声明别名。
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

    public function testInvalidBackedEnumValuesUseLibraryException(): void
    {
        foreach ([['status' => 'bad'], ['statuses' => ['bad']]] as $input) {
            try {
                $this->mapper()->simpleMap($input, new CompatibilityEnumDto());
                $this->fail('Expected JsonMapper_Exception was not thrown.');
            } catch (\JsonMapper_Exception $e) {
                $this->assertInstanceOf(\ValueError::class, $e->getPrevious());
            }
        }
    }

    public function testMapArrayClassMapAcceptsBothLeadingSlashKeyForms(): void
    {
        $cases = [
            ['\\' . CompatibilityShape::class, CompatibilityShape::class],
            [CompatibilityShape::class, '\\' . CompatibilityShape::class],
        ];
        foreach ($cases as [$registeredKey, $inputType]) {
            $mapper = $this->mapper();
            $mapper->classMap[$registeredKey] = CompatibilityCircle::class;

            $result = $mapper->mapArray([['name' => 'ok']], [], $inputType);

            $this->assertInstanceOf(CompatibilityCircle::class, $result[0]);
            $this->assertSame('ok', $result[0]->name);
        }
    }

    public function testMapArrayClassFactoriesAcceptBothLeadingSlashKeyForms(): void
    {
        $cases = [
            ['\\' . CompatibilityShape::class, CompatibilityShape::class],
            [CompatibilityShape::class, '\\' . CompatibilityShape::class],
        ];
        foreach ($cases as [$registeredKey, $inputType]) {
            $mapper = $this->mapper();
            $mapper->classFactories[$registeredKey] = static fn () => new CompatibilityCircle();

            $result = $mapper->mapArray([['name' => 'ok']], [], $inputType);

            $this->assertInstanceOf(CompatibilityCircle::class, $result[0]);
            $this->assertSame('ok', $result[0]->name);
        }
    }

    public function testIterablePropertiesAndSettersAcceptArrayInput(): void
    {
        $propertyDto = $this->mapper()->simpleMap(['items' => [1, 2]], new CompatibilityIterablePropertyDto());
        $setterDto = $this->mapper()->simpleMap(['items' => [1, 2]], new CompatibilityIterableSetterDto());

        $this->assertSame([1, 2], $propertyDto->items);
        $this->assertSame([1, 2], $setterDto->getItems());
    }

    public function testUnsupportedBuiltinTypeThrowsLibraryException(): void
    {
        $this->expectException(\JsonMapper_Exception::class);
        $this->expectExceptionMessage('built-in type');
        $this->mapper()->simpleMap(['callback' => ['strlen']], new CompatibilityCallableSetterDto());
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

enum CompatibilityStatusEnum: string
{
    case ACTIVE = 'active';
}

class CompatibilityEnumDto
{
    public CompatibilityStatusEnum $status;

    /** @var CompatibilityStatusEnum[] */
    public array $statuses = [];
}

interface CompatibilityShape
{
}

class CompatibilityCircle implements CompatibilityShape
{
    public string $name = '';
}

class CompatibilityIterablePropertyDto
{
    public iterable $items = [];
}

class CompatibilityIterableSetterDto
{
    private iterable $items = [];

    public function setItems(iterable $items): void
    {
        $this->items = $items;
    }

    public function getItems(): iterable
    {
        return $this->items;
    }
}

class CompatibilityCallableSetterDto
{
    private $callback;

    public function setCallback(callable $callback): void
    {
        $this->callback = $callback;
    }

    public function getCallback(): callable
    {
        return $this->callback;
    }
}
