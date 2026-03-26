<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\JsonMapper;
use Hyperf\DTO\Annotation\ArrayType;
use Hyperf\DTO\Type\PhpType;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class JsonMapperTest extends TestCase
{
    private JsonMapper $jsonMapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jsonMapper = new JsonMapper();
        $this->jsonMapper->bIgnoreVisibility = true;
        $this->jsonMapper->bEnforceMapType = false;
        $this->jsonMapper->bStrictNullTypes = false;
    }

    public function testMapSimpleDto(): void
    {
        $data = ['name' => 'John', 'age' => 30];
        $dto = new JsonMapperSimpleTestDto();

        $result = $this->jsonMapper->map($data, $dto);

        $this->assertSame('John', $result->name);
        $this->assertSame(30, $result->age);
    }

    public function testMapWithNullableProperty(): void
    {
        $data = ['name' => 'Test', 'age' => null];
        $dto = new JsonMapperSimpleTestDto();

        $result = $this->jsonMapper->map($data, $dto);

        $this->assertSame('Test', $result->name);
        $this->assertNull($result->age);
    }

    public function testParseAnnotationsNewWithArrayTypeAttribute(): void
    {
        $rc = new ReflectionClass(JsonMapperArrayTypeTestDto::class);
        $property = $rc->getProperty('intArray');

        $annotations = $this->jsonMapper->parseAnnotationsNew($rc, $property, null);

        $this->assertArrayHasKey('var', $annotations);
        $this->assertSame('int[]', $annotations['var'][0]);
    }

    public function testParseAnnotationsNewWithDocBlock(): void
    {
        $rc = new ReflectionClass(JsonMapperDocBlockTestDto::class);
        $property = $rc->getProperty('name');

        $annotations = $this->jsonMapper->parseAnnotationsNew($rc, $property, $property->getDocComment());

        $this->assertIsArray($annotations);
    }

    public function testParseAnnotationsNewWithNonStringDocBlock(): void
    {
        $rc = new ReflectionClass(JsonMapperSimpleTestDto::class);
        $property = $rc->getProperty('name');

        $annotations = $this->jsonMapper->parseAnnotationsNew($rc, $property, false);

        $this->assertSame([], $annotations);
    }

    public function testMapToClassWithPrivateProperties(): void
    {
        $data = ['name' => 'Private', 'value' => 123];
        $dto = new JsonMapperPrivatePropertyDto();

        $result = $this->jsonMapper->map($data, $dto);

        $this->assertSame('Private', $result->getName());
        $this->assertSame(123, $result->getValue());
    }
}

class JsonMapperSimpleTestDto
{
    public string $name = '';
    public ?int $age = null;
}

class JsonMapperArrayTypeTestDto
{
    #[ArrayType(PhpType::INT)]
    public array $intArray = [];

    public array $tags = [];
}

class JsonMapperDocBlockTestDto
{
    /** @var string */
    public string $name = '';
}

class JsonMapperPrivatePropertyDto
{
    private string $name = '';
    private int $value = 0;

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setValue(int $value): void
    {
        $this->value = $value;
    }
}