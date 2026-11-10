<?php

declare(strict_types=1);

namespace HyperfTest\DTO\JsonMapper;

use Hyperf\DTO\JsonMapper;
use PHPUnit\Framework\TestCase;

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

        $this->assertInstanceOf(\stdClass::class, $dto->internalData['notype']);
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

        $this->expectException(\JsonMapper_Exception::class);
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
     * DateTime|string 是多成员联合类型：不能取首成员 DateTime 作为映射目标，
     * 否则字符串也会被构造为 DateTime（历史缺陷），这里按原值写入。
     */
    public function testNativeUnionPropertyKeepsRawValue(): void
    {
        $dto = $this->mapper()->simpleMap([
            'dateOrString' => 'hello',
            'scalarUnion' => 5,
        ], new UpstreamUnionDto());

        $this->assertSame('hello', $dto->dateOrString);
        $this->assertSame(5, $dto->scalarUnion);
    }

    public function testUnionDocblockKeepsRawValue(): void
    {
        $dto = $this->mapper()->simpleMap(
            ['docValue' => 'world'],
            new UpstreamUnionDto()
        );

        $this->assertSame('world', $dto->docValue);
    }

    public function testUnionPropertyStillEnforcesPhpNativeType(): void
    {
        try {
            $this->mapper()->simpleMap(['dateOrString' => ['x']], new UpstreamUnionDto());
            $this->fail('Expected JsonMapper_Exception was not thrown.');
        } catch (\JsonMapper_Exception $exception) {
            $this->assertInstanceOf(\TypeError::class, $exception->getPrevious());
        }
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
    public \DateTime|string $dateOrString;

    /** @var \DateTime|string */
    public $docValue;

    public int|string|null $scalarUnion = null;
}
