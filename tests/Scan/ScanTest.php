<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Scan;

use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\DTO\Annotation\Validation\Validation;
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
        AnnotationCollector::clear();
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

    public function testValidationManagerWithArrayRule(): void
    {
        $annotation = new Validation(['string', 'min:5']);
        $annotation->collectProperty(ArrayRuleScanTestDto::class, 'value');

        $manager = new ValidationManager();
        $manager->generateValidation(ArrayRuleScanTestDto::class, 'value');

        $data = $manager->getData(ArrayRuleScanTestDto::class);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('rule', $data);
        $this->assertArrayHasKey('value', $data['rule']);
        // Rules should be flattened: ['string', 'min:5'], NOT [['string', 'min:5']]
        $this->assertSame(['string', 'min:5'], $data['rule']['value']);
    }

    public function testValidationManagerWithArrayRuleAndArrayMessages(): void
    {
        $annotation = new Validation(
            ['string', 'min:5'],
            ['string failed', 'min failed']
        );
        $annotation->collectProperty(ArrayRuleScanTestDto::class, 'value');

        $manager = new ValidationManager();
        $manager->generateValidation(ArrayRuleScanTestDto::class, 'value');

        $data = $manager->getData(ArrayRuleScanTestDto::class);
        $this->assertIsArray($data);
        // Rules should be flattened
        $this->assertSame(['string', 'min:5'], $data['rule']['value']);
        // Messages should be mapped by rule name
        $this->assertArrayHasKey('messages', $data);
        $this->assertSame('string failed', $data['messages']['value.string']);
        $this->assertSame('min failed', $data['messages']['value.min']);
    }

    public function testValidationManagerWithStringRule(): void
    {
        $annotation = new Validation('string|min:5');
        $annotation->collectProperty(ArrayRuleScanTestDto::class, 'value');

        $manager = new ValidationManager();
        $manager->generateValidation(ArrayRuleScanTestDto::class, 'value');

        $data = $manager->getData(ArrayRuleScanTestDto::class);
        $this->assertSame(['string', 'min:5'], $data['rule']['value']);
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

class ArrayRuleScanTestDto
{
    public string $value = '';
}