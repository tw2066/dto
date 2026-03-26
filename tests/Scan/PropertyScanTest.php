<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Scan;

use Hyperf\DTO\Scan\Property;
use Hyperf\DTO\Scan\PropertyManager;
use Hyperf\DTO\Scan\ValidationManager;
use Hyperf\DTO\Scan\MethodParametersManager;
use Hyperf\DTO\Scan\PropertyEnum;
use Hyperf\DTO\DtoCommon;
use PHPUnit\Framework\TestCase;

class PropertyScanTest extends TestCase
{
    public function testPropertyDefaultValues(): void
    {
        $property = new Property();

        $this->assertTrue($property->isSimpleType);
        $this->assertNull($property->phpSimpleType);
        $this->assertNull($property->className);
        $this->assertNull($property->arrClassName);
        $this->assertNull($property->arrSimpleType);
        $this->assertNull($property->enum);
        $this->assertNull($property->alias);
    }

    public function testPropertyIsSimpleArray(): void
    {
        $property = new Property();
        $property->isSimpleType = true;
        $property->phpSimpleType = 'array';

        $this->assertTrue($property->isSimpleArray());
    }

    public function testPropertyIsNotSimpleArrayWhenNotSimpleType(): void
    {
        $property = new Property();
        $property->isSimpleType = false;
        $property->phpSimpleType = 'array';

        $this->assertFalse($property->isSimpleArray());
    }

    public function testPropertyIsSimpleTypeArray(): void
    {
        $property = new Property();
        $property->isSimpleType = false;
        $property->phpSimpleType = 'array';
        $property->arrSimpleType = 'string';

        $this->assertTrue($property->isSimpleTypeArray());
    }

    public function testPropertyIsClassArray(): void
    {
        $property = new Property();
        $property->isSimpleType = false;
        $property->phpSimpleType = 'array';
        $property->arrClassName = \DateTime::class;

        $this->assertTrue($property->isClassArray());
    }

    public function testPropertyManagerWithExistingClass(): void
    {
        $dtoCommon = new DtoCommon();
        $propertyEnum = new PropertyEnum();
        $manager = new PropertyManager($dtoCommon, $propertyEnum);

        $properties = $manager->getPropertyByClass(PropScanTestDto::class);
        $this->assertIsArray($properties);
    }

    public function testPropertyManagerGetPropertyNotFound(): void
    {
        $dtoCommon = new DtoCommon();
        $propertyEnum = new PropertyEnum();
        $manager = new PropertyManager($dtoCommon, $propertyEnum);

        $property = $manager->getProperty(PropScanTestDto::class, 'nonExistent');
        $this->assertNull($property);
    }

    public function testValidationManagerGetData(): void
    {
        $manager = new ValidationManager();
        $data = $manager->getData('UnknownClass');
        $this->assertSame([], $data);
    }

    public function testPropertyEnumWithNonEnum(): void
    {
        $propertyEnum = new PropertyEnum();
        $result = $propertyEnum->get(\stdClass::class);
        $this->assertNull($result);
    }
}

class PropScanTestDto
{
    public string $name = '';
    public int $age = 0;
}