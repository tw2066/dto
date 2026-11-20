<?php

declare(strict_types=1);

namespace HyperfTest\DTO\JsonMapper;

use DateTime;
use Hyperf\DTO\JsonMapper;
use JsonMapper_Exception;
use PHPUnit\Framework\TestCase;
use stdClass;
use TypeError;

/**
 * 改编自 netresearch/jsonmapper（OSL-3.0）的 tests/SimpleTest.php、MixedType_PHP80_Test.php
 * 与 tests/ObjectTest.php 中的标量/可空/联合类型场景，入口统一为 simpleMap()。
 *
 * 与上游的差异（本项目 4.0 的显式语义）：
 * - 只按 primaryKey 精确读取，不做 snake_case/连字符/大小写回退；
 * - 多成员联合类型无法确定目标类时按原值写入，交由 PHP 原生类型检查；
 * - 不支持 undefinedPropertyHandler / bExceptionOnMissingData / postMapping 等钩子。
 *
 * @internal
 * @coversNothing
 */
class JsonMapperSimpleTypesTest extends TestCase
{
    public function testDocblockScalarTypesAreConverted(): void
    {
        $dto = $this->mapper()->simpleMap([
            'str' => 'stringvalue',
            'fl' => '1.2',
            'pbool' => '1',
            'pboolean' => '0',
            'pint' => '123',
            'pinteger' => '12345',
            'pnullableInt' => '12345',
            'pnullableString' => 0,
        ], new UpstreamScalarDto());

        $this->assertSame('stringvalue', $dto->str);
        $this->assertSame(1.2, $dto->fl);
        $this->assertTrue($dto->pbool);
        $this->assertFalse($dto->pboolean);
        $this->assertSame(123, $dto->pint);
        $this->assertSame(12345, $dto->pinteger);
        $this->assertSame(12345, $dto->pnullableInt);
        $this->assertSame('0', $dto->pnullableString);
    }

    public function testNullableDocblockValuesAcceptNull(): void
    {
        $dto = $this->mapper()->simpleMap([
            'pnullableInt' => null,
            'pnullableString' => null,
        ], new UpstreamScalarDto());

        $this->assertNull($dto->pnullableInt);
        $this->assertNull($dto->pnullableString);
    }

    public function testMixedPropertyKeepsRawValue(): void
    {
        $dto = $this->mapper()->simpleMap(['mixed' => 12345], new UpstreamScalarDto());
        $this->assertSame(12345, $dto->mixed);

        $dto = $this->mapper()->simpleMap(['docMixed' => '12345'], new UpstreamScalarDto());
        $this->assertSame('12345', $dto->docMixed);

        $object = (object) ['k' => 'v'];
        $dto = $this->mapper()->simpleMap(['docMixed' => $object], new UpstreamScalarDto());
        $this->assertSame($object, $dto->docMixed);
    }

    public function testUntypedPropertyKeepsRawValue(): void
    {
        $object = (object) ['k' => 'v'];
        $dto = $this->mapper()->simpleMap([
            'notype' => $object,
            'nodocblock' => 'blubb',
        ], new UpstreamScalarDto());

        $this->assertSame($object, $dto->notype);
        $this->assertSame('blubb', $dto->nodocblock);
    }

    public function testSetterOnlyFieldsUseTypeHintDocblockOrRawValue(): void
    {
        $dto = $this->mapper()->simpleMap([
            'simpleSetterOnlyTypeHint' => ['pStr' => 'typehint'],
            'simpleSetterOnlyDocblock' => (object) ['pStr' => 'docblock'],
            'simpleSetterOnlyNoType' => (object) ['pStr' => 'notype'],
        ], new UpstreamSetterOnlyDto());

        $this->assertInstanceOf(UpstreamSetterPlain::class, $dto->internalData['typehint']);
        $this->assertSame('typehint', $dto->internalData['typehint']->pStr);

        $this->assertInstanceOf(UpstreamSetterPlain::class, $dto->internalData['docblock']);
        $this->assertSame('docblock', $dto->internalData['docblock']->pStr);

        $this->assertInstanceOf(stdClass::class, $dto->internalData['notype']);
        $this->assertSame('notype', $dto->internalData['notype']->pStr);
    }

    public function testSetterIsPreferredOverDeclaredProperty(): void
    {
        $dto = $this->mapper()->simpleMap(
            ['setterPreferredOverProperty' => 'foo'],
            new UpstreamSetterPreferredDto()
        );

        $this->assertSame('set via setter: foo', $dto->setterPreferredOverProperty);
    }

    public function testNonNullableValueRejectsNullWithStrictTypes(): void
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;

        $this->expectException(JsonMapper_Exception::class);
        $this->expectExceptionMessage(
            'JSON property "str" in class "' . UpstreamScalarDto::class . '" must not be NULL'
        );

        $mapper->simpleMap(['str' => null], new UpstreamScalarDto());
    }

    public function testLooseNullTypesAssignNull(): void
    {
        $dto = $this->mapper()->simpleMap(['str' => null], new UpstreamScalarDto());

        $this->assertNull($dto->str);
    }

    /**
     * 联合类型统一按首成员的单类型处理：DateTime|string → DateTime，null|int|string → string。
     */
    public function testNativeUnionPropertyUsesFirstMember(): void
    {
        $date = new DateTime('2026-01-01');
        $dto = $this->mapper()->simpleMap([
            'dateOrString' => $date,
            'scalarUnion' => 5,
        ], new UpstreamUnionDto());

        $this->assertSame($date, $dto->dateOrString);
        $this->assertSame('5', $dto->scalarUnion);
    }

    public function testNativeUnionArrayInputMapsFirstMemberClass(): void
    {
        $dto = $this->mapper()->simpleMap(['dateOrString' => ['x']], new UpstreamUnionDto());

        $this->assertInstanceOf(DateTime::class, $dto->dateOrString);
    }

    public function testUnionDocblockWithoutNativeTypeUsesDerivedStringType(): void
    {
        $dto = $this->mapper()->simpleMap(
            ['docValue' => 'world'],
            new UpstreamUnionDto()
        );

        $this->assertSame('world', $dto->docValue);
    }

    /**
     * 与 Hyperf AbstractCallableDefinitionCollector 一致：DTO 属性联合类型取第一个成员。
     */
    public function testUnionPropertyWithWideSetterUsesFirstMember(): void
    {
        $fooB = new UnionFirstFooB();
        $fooB->b = 'v';

        $dto = $this->mapper()->simpleMap(['value' => $fooB], new UnionFirstWideSetterDto());

        $this->assertInstanceOf(UnionFirstFooA::class, $dto->value);
        $this->assertSame('', $dto->value->a);
    }

    public function testUnionPropertyWithWideSetterUsesFirstMemberForScalarUnion(): void
    {
        // 首成员是 DateTime，字符串会按 DateTime 目标处理并抛映射异常
        $this->expectException(JsonMapper_Exception::class);
        $this->mapper()->simpleMap(['value' => '2026-01-01'], new UnionFirstDateDto());
    }

    public function testScalarUnionWithWideSetterUsesFirstReflectionType(): void
    {
        // int|string 的反射首成员是 string
        $dto = $this->mapper()->simpleMap(['value' => 5], new UnionScalarWideDto());

        $this->assertSame('5', $dto->value);
    }

    public function testEnumWithWideSetterUsesPropertyEnumType(): void
    {
        $dto = $this->mapper()->simpleMap(['value' => 'a'], new UnionEnumWideDto());

        $this->assertSame(UnionStatus::A, $dto->value);
    }

    public function testSimpleTypeWithWideSetterUsesPropertyType(): void
    {
        $dto = $this->mapper()->simpleMap(['value' => '5'], new SimpleIntWideDto());

        $this->assertSame(5, $dto->value);
    }

    private function mapper(): JsonMapper
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $mapper->bStrictNullTypes = false;
        return $mapper;
    }
}

class UpstreamScalarDto
{
    /** @var string */
    public $str;

    /** @var float */
    public $fl;

    /** @var bool */
    public $pbool;

    /** @var bool */
    public $pboolean;

    /** @var int */
    public $pint;

    /** @var int */
    public $pinteger;

    /** @var null|int */
    public $pnullableInt;

    /** @var ?string */
    public $pnullableString;

    /** @var mixed */
    public $mixed;

    /** @var mixed */
    public $docMixed;

    public $notype;

    public $nodocblock;
}

class UpstreamSetterPlain
{
    /** @var string */
    public $pStr;
}

class UpstreamSetterOnlyDto
{
    public array $internalData = [];

    public function setSimpleSetterOnlyTypeHint(UpstreamSetterPlain $plain): void
    {
        $this->internalData['typehint'] = $plain;
    }

    /**
     * @param UpstreamSetterPlain $plain
     */
    public function setSimpleSetterOnlyDocblock($plain): void
    {
        $this->internalData['docblock'] = $plain;
    }

    public function setSimpleSetterOnlyNoType($value): void
    {
        $this->internalData['notype'] = $value;
    }
}

class UpstreamSetterPreferredDto
{
    /** @var string */
    public $setterPreferredOverProperty;

    public function setSetterPreferredOverProperty($value): void
    {
        $this->setterPreferredOverProperty = 'set via setter: ' . $value;
    }
}

class UpstreamUnionDto
{
    public DateTime|string $dateOrString;

    /** @var DateTime|string */
    public $docValue;

    public null|int|string $scalarUnion = null;
}

class UnionFirstFooA
{
    public string $a = '';
}

class UnionFirstFooB
{
    public string $b = '';
}

class UnionFirstWideSetterDto
{
    public UnionFirstFooA|UnionFirstFooB $value;

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }
}

class UnionFirstDateDto
{
    public DateTime|string $value;

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }
}

class UnionScalarWideDto
{
    public int|string $value = 0;

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }
}

enum UnionStatus: string
{
    case A = 'a';

    case B = 'b';
}

class UnionEnumWideDto
{
    public UnionStatus $value;

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }
}

class SimpleIntWideDto
{
    public int $value = 0;

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }
}
