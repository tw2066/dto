<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\DtoCommon;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

class DtoCommonTest extends TestCase
{
    private DtoCommon $dtoCommon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dtoCommon = new DtoCommon();
    }

    public function testGetTypeNameWithSimpleType(): void
    {
        $rc = new ReflectionClass(DtoCommonTestDto::class);
        $property = $rc->getProperty('stringProp');

        $result = $this->dtoCommon->getTypeName($property);

        $this->assertSame('string', $result);
    }

    public function testGetTypeNameWithIntType(): void
    {
        $rc = new ReflectionClass(DtoCommonTestDto::class);
        $property = $rc->getProperty('intProp');

        $result = $this->dtoCommon->getTypeName($property);

        $this->assertSame('int', $result);
    }

    public function testGetTypeNameWithClassType(): void
    {
        $rc = new ReflectionClass(DtoCommonTestDto::class);
        $property = $rc->getProperty('dateProp');

        $result = $this->dtoCommon->getTypeName($property);

        $this->assertSame('\\DateTime', $result);
    }

    public function testGetTypeNameWithUnionType(): void
    {
        $rc = new ReflectionClass(DtoCommonTestDto::class);
        $property = $rc->getProperty('unionProp');

        $result = $this->dtoCommon->getTypeName($property);

        $this->assertSame('\\DateTime', $result);
    }

    public function testGetTypeNameWithoutType(): void
    {
        $rc = new ReflectionClass(DtoCommonTestDto::class);
        $property = $rc->getProperty('noTypeProp');

        $result = $this->dtoCommon->getTypeName($property);

        $this->assertSame('string', $result);
    }

    public function testIsSimpleType(): void
    {
        $this->assertTrue($this->dtoCommon->isSimpleType('string'));
        $this->assertTrue($this->dtoCommon->isSimpleType('int'));
        $this->assertTrue($this->dtoCommon->isSimpleType('bool'));
        $this->assertTrue($this->dtoCommon->isSimpleType('float'));
        $this->assertTrue($this->dtoCommon->isSimpleType('array'));
        $this->assertTrue($this->dtoCommon->isSimpleType('object'));
        $this->assertTrue($this->dtoCommon->isSimpleType('mixed'));
    }

    public function testGetSafeName(): void
    {
        $this->assertSame('hello', $this->dtoCommon->getSafeName('hello'));
    }
}

class DtoCommonTestDto
{
    public string $stringProp = '';
    public int $intProp = 0;
    public \DateTime $dateProp;
    public \DateTime|string $unionProp;
    public $noTypeProp;
}