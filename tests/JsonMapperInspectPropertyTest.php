<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\Annotation\ArrayType;
use Hyperf\DTO\Annotation\JSONField;
use Hyperf\DTO\JsonMapper;
use HyperfTest\DTO\Request\Address;
use ReflectionMethod;

/**
 * JsonMapper::inspectProperty 类型推断分支测试.
 *
 * 优先级: setter 类型声明 > setter @param > 属性(继承链/大小写兜底)
 * > ArrayType 注解 > @var(联合截断) > 构造器 @param > PHP 原生类型.
 *
 * @internal
 * @coversNothing
 */
class JsonMapperInspectPropertyTest extends AbstractTestCase
{
    public function testJsonFieldAliasMapsToPublicPropertyWithoutGeneratedSetter(): void
    {
        $mapper = $this->makeMapper();

        $result = $mapper->map(['user_name' => 'alias-value'], new InspectPublicAliasDto());

        $this->assertSame('alias-value', $result->name);
    }

    public function testLegacyPrivateAliasSetterIsNotUsed(): void
    {
        $mapper = $this->makeMapper();
        $dto = new InspectAliasDto();

        $result = $mapper->map(['user_name' => 'alias-value'], $dto);

        $this->assertSame('', $result->getName());
    }

    public function testSetterTypeDeclarationWinsOverDocblock(): void
    {
        [$hasProperty, $accessor, $type] = $this->inspect(InspectSetterTypeDto::class, 'count');

        $this->assertTrue($hasProperty);
        $this->assertInstanceOf(\ReflectionMethod::class, $accessor);
        // setter 参数声明 int 优先于 @param string
        $this->assertSame('int', $type);
    }

    public function testSetterWithoutTypeFallsBackToParamDocblock(): void
    {
        [, $accessor, $type] = $this->inspect(InspectSetterDocblockDto::class, 'list');

        $this->assertInstanceOf(\ReflectionMethod::class, $accessor);
        // setter @param 走正则解析返回原文，不做命名空间解析
        $this->assertSame('Address[]', $type);
    }

    public function testSetterWithoutTypeAndDocblockReturnsNullType(): void
    {
        [$hasProperty, $accessor, $type, $isNullable] = $this->inspect(InspectSetterUntypedDto::class, 'remark');

        $this->assertTrue($hasProperty);
        $this->assertInstanceOf(\ReflectionMethod::class, $accessor);
        $this->assertNull($type);
        $this->assertTrue($isNullable);
    }

    public function testPropertyFoundThroughInheritanceChain(): void
    {
        [$hasProperty, $accessor, $type] = $this->inspect(InspectChildDto::class, 'parentName');

        $this->assertTrue($hasProperty);
        $this->assertSame('parentName', $accessor->getName());
        $this->assertSame('string', $type);
    }

    public function testCaseVariantPropertyDoesNotMatch(): void
    {
        [$hasProperty] = $this->inspect(InspectCaseDto::class, 'myfield');

        $this->assertFalse($hasProperty);
    }

    public function testConstructorDocblockIgnoredWhenPropertyHasNoType(): void
    {
        // 与上游一致: 属性无原生类型声明时不采用构造器 @param
        [$hasProperty, $accessor, $type] = $this->inspect(InspectCtorDocblockDto::class, 'code');

        $this->assertTrue($hasProperty);
        $this->assertSame('code', $accessor->getName());
        $this->assertNull($type);
    }

    public function testConstructorDocblockProvidesParamTypeForTypedProperty(): void
    {
        [$hasProperty, $accessor, $type] = $this->inspect(InspectCtorTypedDocblockDto::class, 'code');

        $this->assertTrue($hasProperty);
        $this->assertSame('code', $accessor->getName());
        $this->assertSame('int', $type);
    }

    public function testPromotedPropertyUsesConstructorParamArrayType(): void
    {
        $mapper = $this->makeMapper();
        $mapper->bIgnoreVisibility = true;

        $dto = $mapper->map(
            json_decode('{"files":[{"value":"test.txt"}]}'),
            InspectPromotedArrayDto::class
        );

        $this->assertInstanceOf(InspectPromotedFileDto::class, $dto->getFiles()[0]);
        $this->assertSame('test.txt', $dto->getFiles()[0]->value);
    }

    public function testNativeSimpleTypeProperty(): void
    {
        [, , $type, $isNullable] = $this->inspect(InspectNativeTypeDto::class, 'age');

        $this->assertSame('int', $type);
        $this->assertFalse($isNullable);
    }

    public function testNativeClassTypePropertyGetsBackslashPrefix(): void
    {
        [, , $type] = $this->inspect(InspectNativeTypeDto::class, 'child');

        $this->assertSame('\HyperfTest\DTO\InspectChildDto', $type);
    }

    public function testPrivatePropertyWithoutSetterIsNotWritable(): void
    {
        [$hasProperty, $accessor, $type, $isNullable] = $this->inspect(InspectPrivateNoSetterDto::class, 'secret');

        // 存在但不可写: accessor 为 null
        $this->assertTrue($hasProperty);
        $this->assertNull($accessor);
        $this->assertNull($type);
        $this->assertFalse($isNullable);
    }

    public function testPrivatePropertyWithoutSetterIsSilentlyDroppedOnMap(): void
    {
        $mapper = $this->makeMapper();
        $dto = new InspectPrivateNoSetterDto();

        $result = $mapper->map(['secret' => 'new-value'], $dto);

        $this->assertSame('default', $result->getSecret());
    }

    public function testMissingPropertyReturnsFalse(): void
    {
        [$hasProperty, $accessor, $type, $isNullable] = $this->inspect(InspectNativeTypeDto::class, 'notExists');

        $this->assertFalse($hasProperty);
        $this->assertNull($accessor);
        $this->assertNull($type);
        $this->assertFalse($isNullable);
    }

    public function testArrayTypeAnnotationWinsOverVarDocblock(): void
    {
        $mapper = $this->makeMapper();
        $rc = new \ReflectionClass(InspectArrayTypeClassDto::class);
        $property = $rc->getProperty('list');

        $annotations = $mapper->parseAnnotationsNew($rc, $property, $property->getDocComment());

        // ArrayType(类名) 优先于 @var int[]，类数组带反斜杠前缀
        $this->assertSame('\HyperfTest\DTO\Request\Address[]', $annotations['var'][0]);
    }

    private function makeMapper(): JsonMapper
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $mapper->bStrictNullTypes = false;
        return $mapper;
    }

    /**
     * @return array [bool, null|ReflectionMethod|\ReflectionProperty, null|string, bool]
     */
    private function inspect(string $class, string $name): array
    {
        $method = new \ReflectionMethod(JsonMapper::class, 'inspectProperty');
        return $method->invoke($this->makeMapper(), new \ReflectionClass($class), $name);
    }
}

class InspectPublicAliasDto
{
    #[JSONField('user_name')]
    public string $name = '';
}

class InspectAliasDto
{
    private string $name = '';

    public function getName(): string
    {
        return $this->name;
    }

    // 3.x 遗留方法：4.0 不再识别或调用该私有别名 setter。
    private function _set_dto_alias_c56f5648d4c0e137f9de3dfc7a54d55b(string $param): void
    {
        $this->name = $param;
    }
}

class InspectSetterTypeDto
{
    private int $count = 0;

    /**
     * @param string $n
     */
    public function setCount(int $n): void
    {
        $this->count = $n;
    }
}

class InspectSetterDocblockDto
{
    private array $list = [];

    /**
     * @param Address[] $list
     */
    public function setList(array $list): void
    {
        $this->list = $list;
    }
}

class InspectSetterUntypedDto
{
    private $remark;

    public function setRemark($remark): void
    {
        $this->remark = $remark;
    }
}

class InspectParentDto
{
    public string $parentName = '';
}

class InspectChildDto extends InspectParentDto {}

class InspectCaseDto
{
    public string $myField = '';
}

class InspectCtorDocblockDto
{
    public $code;

    /**
     * @param int $code
     */
    public function __construct($code = 0)
    {
        $this->code = $code;
    }
}

class InspectCtorTypedDocblockDto
{
    /**
     * @param int $code 状态码
     */
    public function __construct(public int $code = 0) {}

    public function getCode(): int
    {
        return $this->code;
    }
}

class InspectPromotedArrayDto
{
    /**
     * @param InspectPromotedFileDto[] $files
     */
    public function __construct(private array $files) {}

    public function getFiles(): array
    {
        return $this->files;
    }
}

class InspectPromotedFileDto
{
    public string $value = '';
}

class InspectNativeTypeDto
{
    public int $age = 0;

    public ?InspectChildDto $child = null;
}

class InspectPrivateNoSetterDto
{
    private string $secret = 'default';

    public function getSecret(): string
    {
        return $this->secret;
    }
}

class InspectArrayTypeClassDto
{
    /**
     * @var int[]
     */
    #[ArrayType(Address::class)]
    public array $list = [];
}
