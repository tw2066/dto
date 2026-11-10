<?php

declare(strict_types=1);

namespace HyperfTest\DTO\JsonMapper;

use Hyperf\DTO\JsonMapper;
use PHPUnit\Framework\TestCase;

/**
 * 改编自 netresearch/jsonmapper（OSL-3.0）的 tests/ObjectTest.php 与 tests/OtherTest.php。
 *
 * 覆盖 simpleMap() 的嵌套对象、DateTime、构造函数旁路、可空对象、
 * 严格对象类型检查以及入口参数校验。上游的 map() 专属钩子
 * （undefinedPropertyHandler、missing data、postMapping、logger）不适用。
 *
 * @internal
 * @coversNothing
 */
class JsonMapperObjectsTest extends TestCase
{
    public function testNestedObjectsUseDocblockAndNativeTypes(): void
    {
        $dto = $this->mapper()->simpleMap([
            'plain' => ['pStr' => 'docblock'],
            'nativePlain' => (object) ['pStr' => 'native'],
        ], new UpstreamObjectParentDto());

        $this->assertInstanceOf(UpstreamObjectPlain::class, $dto->plain);
        $this->assertSame('docblock', $dto->plain->pStr);
        $this->assertInstanceOf(UpstreamObjectPlain::class, $dto->nativePlain);
        $this->assertSame('native', $dto->nativePlain->pStr);
    }

    public function testClassStringTargetCreatesInstance(): void
    {
        $dto = $this->mapper()->simpleMap(['pStr' => 'stringvalue'], UpstreamObjectPlain::class);

        $this->assertInstanceOf(UpstreamObjectPlain::class, $dto);
        $this->assertSame('stringvalue', $dto->pStr);
    }

    public function testClassStringTargetWithRequiredConstructorIsMappedWithoutConstructor(): void
    {
        $dto = $this->mapper()->simpleMap([
            'name' => 'mapped',
            'id' => 5,
        ], UpstreamObjectRequiredCtorDto::class);

        $this->assertInstanceOf(UpstreamObjectRequiredCtorDto::class, $dto);
        $this->assertSame('mapped', $dto->name);
        $this->assertSame(5, $dto->id);
    }

    public function testDateTimeIsCreatedFromStringWhenObjectStrictnessDisabled(): void
    {
        $mapper = $this->mapper();
        $mapper->bStrictObjectTypeChecking = false;

        $dto = $mapper->simpleMap([
            'datetime' => '2014-04-01T00:00:00+02:00',
        ], new UpstreamObjectDateTimeDto());

        $this->assertInstanceOf(\DateTime::class, $dto->datetime);
        $this->assertSame('2014-04-01T00:00:00+02:00', $dto->datetime->format('c'));
    }

    public function testDateTimeRejectsScalarUnderStrictObjectTypeChecking(): void
    {
        $this->expectException(\JsonMapper_Exception::class);
        $this->expectExceptionMessage(
            'JSON property "datetime" in class "' . UpstreamObjectDateTimeDto::class
            . '" must be an object of type DateTime, string given'
        );

        $this->mapper()->simpleMap(['datetime' => '2014-04-01'], new UpstreamObjectDateTimeDto());
    }

    public function testDateTimeNullableAcceptsNull(): void
    {
        $dto = $this->mapper()->simpleMap(['nullableDatetime' => null], new UpstreamObjectDateTimeDto());

        $this->assertNull($dto->nullableDatetime);
    }

    public function testNonNullableObjectRejectsNull(): void
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;

        $this->expectException(\JsonMapper_Exception::class);
        $this->expectExceptionMessage(
            'JSON property "plain" in class "' . UpstreamObjectParentDto::class . '" must not be NULL'
        );

        $mapper->simpleMap(['plain' => null], new UpstreamObjectParentDto());
    }

    public function testNullableSetterAcceptsNullAndNestedValue(): void
    {
        $dto = $this->mapper()->simpleMap([
            'nullableObject' => null,
            'docblockNullableObject' => null,
        ], new UpstreamObjectSetterDto());

        $this->assertNull($dto->nullableObject);
        $this->assertNull($dto->docblockNullableObject);

        $dto = $this->mapper()->simpleMap([
            'nullableObject' => ['pStr' => 'nested'],
        ], new UpstreamObjectSetterDto());

        $this->assertInstanceOf(UpstreamObjectPlain::class, $dto->nullableObject);
        $this->assertSame('nested', $dto->nullableObject->pStr);
    }

    public function testExistingObjectOfSameTypeIsAssignedDirectly(): void
    {
        $valueObject = new UpstreamObjectPlain();
        $valueObject->pStr = 'direct';

        $dto = $this->mapper()->simpleMap(['plain' => $valueObject], new UpstreamObjectParentDto());

        $this->assertSame($valueObject, $dto->plain);
    }

    /**
     * 属性名前缀相同时（id / idDate），不得把 idDate 的类型套给 id。
     */
    public function testConstructorPrefixBugDoesNotConfuseTypes(): void
    {
        $mapper = $this->mapper();
        $mapper->bStrictObjectTypeChecking = false;

        $dto = $mapper->simpleMap([
            'id' => 1,
            'idDate' => '2025-02-23',
        ], UpstreamObjectCtorPrefixDto::class);

        $this->assertSame(1, $dto->id);
        $this->assertInstanceOf(\DateTime::class, $dto->idDate);
        $this->assertSame('2025-02-23', $dto->idDate->format('Y-m-d'));
    }

    public function testJsonDecodedObjectInputWorksWithDefaultEnforcement(): void
    {
        $mapper = new JsonMapper();

        $dto = $mapper->simpleMap(json_decode('{"pStr":"x"}'), new UpstreamObjectPlain());

        $this->assertSame('x', $dto->pStr);
    }

    public function testArrayInputIsRejectedWithDefaultEnforcement(): void
    {
        $mapper = new JsonMapper();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'JsonMapper::simpleMap() requires first argument to be an object, array given.'
        );

        $mapper->simpleMap(['pStr' => 'x'], new UpstreamObjectPlain());
    }

    public function testSecondArgumentMustBeObjectOrExistingClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'JsonMapper::simpleMap() requires second argument to be an object or existing class name, NULL given.'
        );

        $this->mapper()->simpleMap([], null);
    }

    public function testUnknownClassNameIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'JsonMapper::simpleMap() requires second argument to be an object or existing class name, string given.'
        );

        $this->mapper()->simpleMap([], 'HyperfTest\DTO\ThisClassDoesNotExist');
    }

    public function testNullInputMapsToDefaultObjectWhenEnforcementDisabled(): void
    {
        $dto = $this->mapper()->simpleMap(null, new UpstreamObjectPlain());

        $this->assertSame('', $dto->pStr);
    }

    public function testMissingFieldsKeepDefaultsAndExtraKeysAreIgnored(): void
    {
        $dto = $this->mapper()->simpleMap([
            'pStr' => 'changed',
            'unknown' => 123,
        ], new UpstreamObjectPlain());

        $this->assertSame('changed', $dto->pStr);
        $this->assertSame('default', $dto->other);
    }

    private function mapper(): JsonMapper
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $mapper->bStrictNullTypes = false;
        return $mapper;
    }
}

class UpstreamObjectPlain
{
    /** @var string */
    public $pStr = '';

    /** @var string */
    public $other = 'default';
}

class UpstreamObjectParentDto
{
    /** @var UpstreamObjectPlain */
    public $plain;

    public UpstreamObjectPlain $nativePlain;
}

class UpstreamObjectDateTimeDto
{
    /** @var \DateTime */
    public $datetime;

    /** @var null|\DateTime */
    public $nullableDatetime;
}

class UpstreamObjectSetterDto
{
    public $nullableObject;

    public $docblockNullableObject;

    public function setNullableObject(?UpstreamObjectPlain $obj = null): void
    {
        $this->nullableObject = $obj;
    }

    /**
     * @param null|UpstreamObjectPlain $obj
     */
    public function setDocblockNullableObject($obj): void
    {
        $this->docblockNullableObject = $obj;
    }
}

class UpstreamObjectRequiredCtorDto
{
    /** @var string */
    public $name = '';

    public function __construct(public int $id) {}
}

class UpstreamObjectCtorPrefixDto
{
    public int $id;

    public \DateTime $idDate;

    public function __construct(\DateTime $idDate, int $id)
    {
        $this->idDate = $idDate;
        $this->id = $id;
    }
}
