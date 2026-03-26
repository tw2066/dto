<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Scan;

use Hyperf\DTO\Scan\Scan;
use Hyperf\DTO\Scan\PropertyManager;
use Hyperf\DTO\Scan\ValidationManager;
use Hyperf\DTO\Scan\MethodParametersManager;
use Hyperf\DTO\Scan\PropertyEnum;
use Hyperf\DTO\Scan\Property;
use Hyperf\DTO\DtoCommon;
use PHPUnit\Framework\TestCase;
use Mockery;

class ScanTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testPropertyEnumWithBackedEnum(): void
    {
        $propertyEnum = new PropertyEnum();
        $result = $propertyEnum->get(ScanTestStatusEnum::class);

        $this->assertNotNull($result);
        $this->assertSame('string', $result->backedType);
        $this->assertContains('active', $result->valueList);
        $this->assertContains('inactive', $result->valueList);
    }

    public function testPropertyEnumWithIntBackedEnum(): void
    {
        $propertyEnum = new PropertyEnum();
        $result = $propertyEnum->get(ScanTestIntStatusEnum::class);

        $this->assertNotNull($result);
        $this->assertSame('int', $result->backedType);
        $this->assertSame([1, 2], $result->valueList);
    }

    public function testPropertyEnumWithNonBackedEnum(): void
    {
        $propertyEnum = new PropertyEnum();
        $result = $propertyEnum->get(ScanTestPureEnum::class);

        $this->assertNull($result);
    }

    public function testValidationManagerGenerateValidation(): void
    {
        $manager = new ValidationManager();
        $manager->generateValidation(SimpleScanTestDto::class, 'name');

        $data = $manager->getData(SimpleScanTestDto::class);
        $this->assertIsArray($data);
    }
}

class SimpleScanTestDto
{
    public string $name = '';
    public int $age = 0;
}

enum ScanTestStatusEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

enum ScanTestIntStatusEnum: int
{
    case Active = 1;
    case Inactive = 2;
}

enum ScanTestPureEnum
{
    case Active;
    case Inactive;
}