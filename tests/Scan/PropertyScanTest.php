<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Scan;

use DateTime;
use Hyperf\DTO\DtoCommon;
use Hyperf\DTO\Scan\Property;
use Hyperf\DTO\Scan\PropertyEnum;
use Hyperf\DTO\Scan\PropertyManager;
use Hyperf\DTO\Scan\ValidationManager;
use Hyperf\Engine\Channel;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Swoole\Coroutine;

/**
 * @internal
 * @coversNothing
 */
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
        $property->arrClassName = DateTime::class;

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

    /**
     * 与 Hyperf AbstractCallableDefinitionCollector 一致：联合类型取第一个成员。
     */
    public function testUnionPropertyRecordsFirstMemberClass(): void
    {
        $manager = new PropertyManager(new DtoCommon(), new PropertyEnum());

        $property = $manager->getProperty(PropScanUnionDto::class, 'value');

        $this->assertNotNull($property);
        $this->assertSame(PropScanUnionA::class, $property->className);
    }

    public function testSingleClassPropertyStillRecordsClassName(): void
    {
        $manager = new PropertyManager(new DtoCommon(), new PropertyEnum());

        $property = $manager->getProperty(PropScanSingleDto::class, 'value');

        $this->assertNotNull($property);
        $this->assertSame(PropScanUnionA::class, $property->className);
    }

    public function testScalarUnionPropertyRecordsFirstReflectionType(): void
    {
        $manager = new PropertyManager(new DtoCommon(), new PropertyEnum());

        $property = $manager->getProperty(PropScanScalarUnionDto::class, 'value');

        $this->assertNotNull($property);
        $this->assertNull($property->className);
        // int|string 的反射首成员是 string
        $this->assertSame('string', $property->phpSimpleType);
    }

    /**
     * 并发首次扫描同一类时，后到的协程必须等扫描完成后再读取元数据，
     * 不能读到只填充了一半的 content。
     */
    public function testConcurrentScanWaitsForCompleteMetadata(): void
    {
        if (! extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole is required for the coroutine scan test.');
        }

        $results = [];
        \Swoole\Coroutine\run(function () use (&$results): void {
            $started = new Channel(1);
            $done = new Channel(2);
            $dtoCommon = new class($started) extends DtoCommon {
                public function __construct(private Channel $started)
                {
                }

                public function inspectPropertyMetadata(ReflectionClass $rc, string $name): array
                {
                    if ($rc->getName() === PropScanConcurrentDto::class && $name === 'first') {
                        $this->started->push(true);
                        // 强制让出，模拟扫描过程中的文件 I/O
                        Coroutine::sleep(0.02);
                    }
                    return parent::inspectPropertyMetadata($rc, $name);
                }
            };
            $manager = new PropertyManager($dtoCommon, new PropertyEnum());

            Coroutine::create(function () use ($manager, &$results, $done): void {
                try {
                    $results['a'] = $manager->getPropertyByClass(PropScanConcurrentDto::class);
                } finally {
                    $done->push(true);
                }
            });
            $started->pop(); // A 已进入扫描并让出
            Coroutine::create(function () use ($manager, &$results, $done): void {
                try {
                    $results['b'] = $manager->getPropertyByClass(PropScanConcurrentDto::class);
                } finally {
                    $done->push(true);
                }
            });
            $done->pop();
            $done->pop();
        });

        $this->assertArrayHasKey('first', $results['a']);
        $this->assertArrayHasKey('second', $results['a']);
        $this->assertArrayHasKey('first', $results['b']);
        $this->assertArrayHasKey('second', $results['b']);
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
        $result = $propertyEnum->get(stdClass::class);
        $this->assertNull($result);
    }
}

class PropScanTestDto
{
    public string $name = '';

    public int $age = 0;
}

class PropScanUnionA
{
    public string $a = '';
}

class PropScanUnionB
{
    public string $b = '';
}

class PropScanUnionDto
{
    public PropScanUnionA|PropScanUnionB $value;

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }
}

class PropScanSingleDto
{
    public PropScanUnionA $value;
}

class PropScanConcurrentDto
{
    public string $first = '';

    public string $second = '';
}

class PropScanScalarUnionDto
{
    public int|string $value = 0;
}
