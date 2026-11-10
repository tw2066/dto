<?php

declare(strict_types=1);

namespace HyperfTest\DTO\JsonMapper;

use Hyperf\DTO\JsonMapper;
use PHPUnit\Framework\TestCase;

/**
 * 改编自 netresearch/jsonmapper（OSL-3.0）的 tests/ArrayTest.php、Array_PHP74_Test.php、
 * Array_PHP80_Test.php 与 tests/ObjectTest.php 中的数组/ArrayObject/ArrayAccess 场景。
 *
 * simpleMap() 保留上游的数组元素类型转换、键名保留、可空元素、
 * ArrayObject/ArrayAccess 和 variadic setter 行为；数组元素 null 的严格检查
 * 由 bStrictNullTypesInArrays 控制（默认开启）。
 *
 * @internal
 * @coversNothing
 */
class JsonMapperArraysTest extends TestCase
{
    public function testSimpleTypeArraysAreConverted(): void
    {
        $dto = $this->mapper()->simpleMap([
            'flArray' => [1.23, 3.14, 2.048],
            'strArray' => ['str', false, 2.048],
            'strArrayV2' => ['str', false, 2.048],
        ], new UpstreamArrayScalarsDto());

        $this->assertSame([1.23, 3.14, 2.048], $dto->flArray);
        $this->assertSame(['str', '', '2.048'], $dto->strArray);
        $this->assertSame(['str', '', '2.048'], $dto->strArrayV2);
    }

    public function testSimpleTypeArraysPreserveObjectKeys(): void
    {
        $dto = $this->mapper()->simpleMap([
            'strArray' => (object) ['en-US' => 'foo', 'de-DE' => 'bar'],
        ], new UpstreamArrayScalarsDto());

        $this->assertSame(['en-US' => 'foo', 'de-DE' => 'bar'], $dto->strArray);
    }

    public function testNullableElementArraysAreConverted(): void
    {
        $dto = $this->mapper()->simpleMap([
            'maybeNull' => ['str', null, 2.048],
            'maybeNullV2' => ['str', null, 2.048],
            'nullableSimple' => null,
        ], new UpstreamArrayNullableDto());

        $this->assertSame(['str', null, '2.048'], $dto->maybeNull);
        $this->assertSame(['str', null, '2.048'], $dto->maybeNullV2);
        $this->assertNull($dto->nullableSimple);
    }

    public function testTypedObjectAndMatrixArraysAreMapped(): void
    {
        $dto = $this->mapper()->simpleMap([
            'typedArray' => [['pStr' => 'stringvalue'], ['pStr' => 'other']],
            'nMatrix' => [[1, 2], [3, 4], [5]],
            'pMultiverse' => [[[['pStr' => 'deep']]]],
        ], new UpstreamArrayTypedDto());

        $this->assertContainsOnlyInstancesOf(UpstreamArrayItem::class, $dto->typedArray);
        $this->assertSame('stringvalue', $dto->typedArray[0]->pStr);
        $this->assertSame('other', $dto->typedArray[1]->pStr);

        $this->assertSame([[1, 2], [3, 4], [5]], $dto->nMatrix);

        $this->assertInstanceOf(UpstreamArrayItem::class, $dto->pMultiverse[0][0][0]);
        $this->assertSame('deep', $dto->pMultiverse[0][0][0]->pStr);
    }

    public function testKeyedDateTimeArrayFromObjectInput(): void
    {
        $mapper = $this->mapper();
        $mapper->bStrictObjectTypeChecking = false;

        $dto = $mapper->simpleMap([
            'typedSimpleArray' => (object) ['en-US' => '2014-01-02'],
        ], new UpstreamArrayDateTimeDto());

        $this->assertArrayHasKey('en-US', $dto->typedSimpleArray);
        $this->assertInstanceOf(\DateTime::class, $dto->typedSimpleArray['en-US']);
        $this->assertSame('2014-01-02', $dto->typedSimpleArray['en-US']->format('Y-m-d'));
    }

    public function testNullArrayElementsAreRejectedByDefault(): void
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;

        $this->expectException(\JsonMapper_Exception::class);
        $this->expectExceptionMessage('JSON property "strArray[1]" must not be NULL');

        $mapper->simpleMap(['strArray' => ['a', null, 'c']], new UpstreamArrayScalarsDto());
    }

    public function testNullArrayElementsCanBeAllowed(): void
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $mapper->bStrictNullTypes = false;
        $mapper->bStrictNullTypesInArrays = false;

        $dto = $mapper->simpleMap(['strArray' => ['a', null, 'c']], new UpstreamArrayScalarsDto());

        $this->assertSame(['a', null, 'c'], $dto->strArray);
    }

    public function testDateTimeArrayAllowsNullElementsWhenConfigured(): void
    {
        $mapper = $this->mapper();
        $mapper->bStrictObjectTypeChecking = false;
        $mapper->bStrictNullTypesInArrays = false;

        $dto = $mapper->simpleMap([
            'typedSimpleArray' => ['2014-01-02', null, '2014-05-07'],
        ], new UpstreamArrayDateTimeDto());

        $this->assertCount(3, $dto->typedSimpleArray);
        $this->assertInstanceOf(\DateTime::class, $dto->typedSimpleArray[0]);
        $this->assertNull($dto->typedSimpleArray[1]);
        $this->assertSame('2014-05-07', $dto->typedSimpleArray[2]->format('Y-m-d'));
    }

    public function testArrayObjectKeepsRawElementValues(): void
    {
        $dto = $this->mapper()->simpleMap([
            'pArrayObject' => [(object) ['pStr' => 'stringvalue'], (object) ['fl' => '1.2']],
            'pNullableArrayObject' => null,
        ], new UpstreamArrayObjectDto());

        $this->assertInstanceOf(\ArrayObject::class, $dto->pArrayObject);
        $this->assertCount(2, $dto->pArrayObject);
        $this->assertContainsOnlyInstancesOf(\stdClass::class, $dto->pArrayObject);
        $this->assertSame('stringvalue', $dto->pArrayObject[0]->pStr);
        $this->assertSame('1.2', $dto->pArrayObject[1]->fl);
        $this->assertNull($dto->pNullableArrayObject);
    }

    public function testNonNullableArrayObjectRejectsNull(): void
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;

        $this->expectException(\JsonMapper_Exception::class);
        $this->expectExceptionMessage(
            'JSON property "pArrayObject" in class "' . UpstreamArrayObjectDto::class . '" must not be NULL'
        );

        $mapper->simpleMap(['pArrayObject' => null], new UpstreamArrayObjectDto());
    }

    public function testTypedArrayObjectMapsElementType(): void
    {
        $dto = $this->mapper()->simpleMap([
            'pTypedArrayObject' => [['pStr' => 'stringvalue'], ['pStr' => '1.2']],
        ], new UpstreamArrayObjectDto());

        $this->assertInstanceOf(\ArrayObject::class, $dto->pTypedArrayObject);
        $this->assertContainsOnlyInstancesOf(UpstreamArrayItem::class, $dto->pTypedArrayObject);
        $this->assertSame('stringvalue', $dto->pTypedArrayObject[0]->pStr);
    }

    public function testSimpleArrayObjectConvertsScalarValues(): void
    {
        $dto = $this->mapper()->simpleMap([
            'pSimpleArrayObject' => (object) ['eins' => '1', 'zwei' => '1.2'],
        ], new UpstreamArrayObjectDto());

        $this->assertInstanceOf(\ArrayObject::class, $dto->pSimpleArrayObject);
        $this->assertSame(1, $dto->pSimpleArrayObject['eins']);
        $this->assertSame(1, $dto->pSimpleArrayObject['zwei']);
    }

    public function testArrayObjectListMapsEveryElement(): void
    {
        $dto = $this->mapper()->simpleMap([
            'pArrayObjectList' => [(object) ['x' => 'X'], (object) ['y' => 'Y']],
        ], new UpstreamArrayObjectDto());

        $this->assertCount(2, $dto->pArrayObjectList);
        $this->assertContainsOnlyInstancesOf(\ArrayObject::class, $dto->pArrayObjectList);
        $this->assertSame(['x' => 'X'], $dto->pArrayObjectList[0]->getArrayCopy());
        $this->assertSame(['y' => 'Y'], $dto->pArrayObjectList[1]->getArrayCopy());
    }

    public function testArrayAccessCollectionReceivesOffsets(): void
    {
        $dto = $this->mapper()->simpleMap([
            'pArrayAccessCollection' => (object) ['eins' => 1, 'zwei' => 'two'],
        ], new UpstreamArrayAccessDto());

        $this->assertInstanceOf(UpstreamArrayAccessCollection::class, $dto->pArrayAccessCollection);
        $this->assertInstanceOf(\Traversable::class, $dto->pArrayAccessCollection);
        $this->assertSame(1, $dto->pArrayAccessCollection['eins']);
        $this->assertSame('two', $dto->pArrayAccessCollection['zwei']);
    }

    public function testArrayAccessObjectIsMappedAsNestedObject(): void
    {
        $dto = $this->mapper()->simpleMap([
            'pArrayAccessObject' => (object) [
                'eins' => 1,
                'zwei' => 'two',
                'valueObject' => (object) ['pStr' => 'mapped'],
            ],
        ], new UpstreamArrayAccessDto());

        $this->assertInstanceOf(UpstreamArrayAccessObject::class, $dto->pArrayAccessObject);
        $this->assertSame(1, $dto->pArrayAccessObject['eins']);
        $this->assertSame('two', $dto->pArrayAccessObject['zwei']);
        $this->assertInstanceOf(UpstreamArrayItem::class, $dto->pArrayAccessObject['valueObject']);
        $this->assertSame('mapped', $dto->pArrayAccessObject['valueObject']->pStr);
    }

    public function testInvalidScalarForArrayPropertyThrows(): void
    {
        $this->expectException(\JsonMapper_Exception::class);
        $this->expectExceptionMessage(
            'JSON property "flArray" in class "' . UpstreamArrayScalarsDto::class
            . '" must be an array of float, integer given'
        );

        $this->mapper()->simpleMap(['flArray' => 4], new UpstreamArrayScalarsDto());
    }

    public function testObjectElementInScalarArrayThrows(): void
    {
        $this->expectException(\JsonMapper_Exception::class);
        $this->expectExceptionMessage(
            'JSON property "strArray" is an array of type "string" but contained a value of type "object"'
        );

        $this->mapper()->simpleMap(['strArray' => [new \stdClass()]], new UpstreamArrayScalarsDto());
    }

    public function testStrictObjectTypeCheckingAppliesToArrayElements(): void
    {
        $this->expectException(\JsonMapper_Exception::class);
        $this->expectExceptionMessage('JSON property "typedArray[0]" must be an object, string given');

        $this->mapper()->simpleMap(['typedArray' => ['abc']], new UpstreamArrayTypedDto());
    }

    public function testVariadicSettersAcceptElementLists(): void
    {
        $mapper = $this->mapper();
        $mapper->bStrictObjectTypeChecking = false;

        $dto = $mapper->simpleMap([
            'variadicInt' => [1, 2, 3],
            'variadicDateTime' => ['2014-01-02', '2014-05-07'],
        ], new UpstreamVariadicDto());

        $this->assertSame([1, 2, 3], $dto->getVariadicInt());
        $this->assertCount(2, $dto->getVariadicDateTime());
        $this->assertContainsOnlyInstancesOf(\DateTime::class, $dto->getVariadicDateTime());
        $this->assertSame('2014-01-02', $dto->getVariadicDateTime()[0]->format('Y-m-d'));
        $this->assertSame('2014-05-07', $dto->getVariadicDateTime()[1]->format('Y-m-d'));
    }

    public function testVariadicSetterWithMultipleParametersReceivesArray(): void
    {
        $dto = $this->mapper()->simpleMap(
            ['multipleParams' => [23]],
            new UpstreamVariadicDto()
        );

        $this->assertSame([23], $dto->multipleParamsVal);
    }

    public function testMapArrayPreservesListAndStrangeKeys(): void
    {
        $mapper = new JsonMapper();

        $this->assertSame([1, 2, 3], $mapper->mapArray([1, 2, 3], []));
        $this->assertSame(
            ['en-US' => 'foo', 'de-DE' => 'bar'],
            $mapper->mapArray(['en-US' => 'foo', 'de-DE' => 'bar'], [])
        );
    }

    private function mapper(): JsonMapper
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $mapper->bStrictNullTypes = false;
        return $mapper;
    }
}

class UpstreamArrayScalarsDto
{
    /** @var float[] */
    public array $flArray = [];

    /** @var string[] */
    public array $strArray = [];

    /** @var array[string] */
    public array $strArrayV2 = [];
}

class UpstreamArrayNullableDto
{
    /** @var array[string|null] */
    public array $maybeNull = [];

    /** @var array[?string] */
    public array $maybeNullV2 = [];

    /** @var null|string[] */
    public $nullableSimple;
}

class UpstreamArrayItem
{
    /** @var string */
    public $pStr;
}

class UpstreamArrayTypedDto
{
    /** @var UpstreamArrayItem[] */
    public array $typedArray = [];

    /** @var int[][] */
    public array $nMatrix = [];

    /** @var UpstreamArrayItem[][][] */
    public array $pMultiverse = [];
}

class UpstreamArrayDateTimeDto
{
    /** @var \DateTime[] */
    public array $typedSimpleArray = [];
}

class UpstreamArrayObjectDto
{
    /** @var \ArrayObject */
    public $pArrayObject;

    /** @var null|\ArrayObject */
    public $pNullableArrayObject;

    /** @var \ArrayObject[UpstreamArrayItem] */
    public $pTypedArrayObject;

    /** @var \ArrayObject[int] */
    public $pSimpleArrayObject;

    /** @var \ArrayObject[] */
    public array $pArrayObjectList = [];
}

class UpstreamArrayAccessDto
{
    /** @var UpstreamArrayAccessObject */
    public $pArrayAccessObject;

    /** @var UpstreamArrayAccessCollection */
    public $pArrayAccessCollection;
}

class UpstreamArrayAccessObject implements \ArrayAccess
{
    /** @var int */
    public $eins;

    /** @var string */
    public $zwei;

    /** @var UpstreamArrayItem */
    public $valueObject;

    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return isset($this->{$offset});
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->{$offset} ?? null;
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->{$offset} = $value;
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->{$offset});
    }
}

class UpstreamArrayAccessCollection implements \ArrayAccess, \IteratorAggregate
{
    private array $data = [];

    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->data[$offset] ?? null;
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new \ArrayIterator($this->data);
    }
}

class UpstreamVariadicDto
{
    public array $multipleParamsVal = [];

    private array $variadicInt = [];

    private array $variadicDateTime = [];

    /**
     * @param int[] $items
     */
    public function setVariadicInt(int ...$items): self
    {
        $this->variadicInt = $items;
        return $this;
    }

    public function getVariadicInt(): array
    {
        return $this->variadicInt;
    }

    /**
     * @param \DateTime[] $items
     */
    public function setVariadicDateTime(\DateTime ...$items): self
    {
        $this->variadicDateTime = $items;
        return $this;
    }

    public function getVariadicDateTime(): array
    {
        return $this->variadicDateTime;
    }

    public function setMultipleParams(array $param, int ...$dummy): void
    {
        $this->multipleParamsVal = $param;
    }
}
