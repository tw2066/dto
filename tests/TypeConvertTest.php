<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\DtoCommon;
use Hyperf\DTO\Scan\PropertyEnum;
use Hyperf\DTO\Type\Convert;
use Hyperf\DTO\Type\ConvertCustom;
use Hyperf\DTO\Type\PhpType;
use PHPUnit\Framework\TestCase;

class TypeConvertTest extends TestCase
{
    public function testConvertEnumCases(): void
    {
        $this->assertSame('camel', Convert::CAMEL->value);
        $this->assertSame('snake', Convert::SNAKE->value);
        $this->assertSame('studly', Convert::STUDLY->value);
        $this->assertSame('none', Convert::NONE->value);
        $this->assertSame('custom', Convert::CUSTOM->value);
    }

    public function testConvertCamel(): void
    {
        $result = Convert::CAMEL->getValue('hello_world');
        $this->assertSame('helloWorld', $result);
    }

    public function testConvertSnake(): void
    {
        $result = Convert::SNAKE->getValue('helloWorld');
        $this->assertSame('hello_world', $result);
    }

    public function testConvertStudly(): void
    {
        $result = Convert::STUDLY->getValue('hello_world');
        $this->assertSame('HelloWorld', $result);
    }

    public function testConvertNone(): void
    {
        $result = Convert::NONE->getValue('hello_world');
        $this->assertSame('hello_world', $result);
    }

    public function testConvertCustom(): void
    {
        ConvertCustom::setClosure(fn ($data) => strtoupper($data));
        $result = Convert::CUSTOM->getValue('hello');
        $this->assertSame('HELLO', $result);
    }

    public function testPhpTypeEnum(): void
    {
        $this->assertSame('bool', PhpType::BOOL->getValue());
        $this->assertSame('float', PhpType::FLOAT->getValue());
        $this->assertSame('string', PhpType::STRING->getValue());
        $this->assertSame('array', PhpType::ARRAY->getValue());
        $this->assertSame('object', PhpType::OBJECT->getValue());
        $this->assertSame('int', PhpType::INT->getValue());
    }

    public function testPropertyEnumWithBackedEnum(): void
    {
        $propertyEnum = new PropertyEnum();
        $result = $propertyEnum->get(TestStatusEnum::class);
        $this->assertNotNull($result);
        $this->assertSame('string', $result->backedType);
        $this->assertSame(TestStatusEnum::class, $result->className);
        $this->assertContains('active', $result->valueList);
    }

    public function testPropertyEnumWithIntBackedEnum(): void
    {
        $propertyEnum = new PropertyEnum();
        $result = $propertyEnum->get(TestIntStatusEnum::class);
        $this->assertNotNull($result);
        $this->assertSame('int', $result->backedType);
        $this->assertSame(TestIntStatusEnum::class, $result->className);
        $this->assertSame([1, 2, 3], $result->valueList);
    }

    public function testPropertyEnumWithNonEnumClass(): void
    {
        $propertyEnum = new PropertyEnum();
        $result = $propertyEnum->get(\stdClass::class);
        $this->assertNull($result);
    }

    public function testDtoCommonIsSimpleType(): void
    {
        $dtoCommon = new DtoCommon();

        $this->assertTrue($dtoCommon->isSimpleType('string'));
        $this->assertTrue($dtoCommon->isSimpleType('int'));
        $this->assertTrue($dtoCommon->isSimpleType('bool'));
        $this->assertTrue($dtoCommon->isSimpleType('float'));
        $this->assertTrue($dtoCommon->isSimpleType('array'));
        $this->assertTrue($dtoCommon->isSimpleType('object'));
    }

    public function testDtoCommonMethodsExist(): void
    {
        $dtoCommon = new DtoCommon();

        $this->assertTrue(method_exists($dtoCommon, 'getSafeName'));
        $this->assertTrue(method_exists($dtoCommon, 'getFullNamespace'));
        $this->assertTrue(method_exists($dtoCommon, 'isArrayOfType'));
    }
}

enum TestStatusEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Pending = 'pending';
}

enum TestIntStatusEnum: int
{
    case Active = 1;
    case Inactive = 2;
    case Pending = 3;
}