<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Scan;

use Hyperf\DTO\Annotation\ArrayType;
use Hyperf\DTO\Annotation\JSONField;
use Hyperf\DTO\DtoCommon;
use Hyperf\DTO\Exception\DtoException;
use Hyperf\DTO\Scan\PropertyEnum;
use Hyperf\DTO\Scan\PropertyManager;
use HyperfTest\DTO\AbstractTestCase;
use HyperfTest\DTO\Request\Address;

/**
 * PropertyManager 深度守卫 / 递归去重 / docblock 边界测试.
 *
 * @internal
 * @coversNothing
 */
class PropertyManagerDepthTest extends AbstractTestCase
{
    public function testExceedingMaxDepthThrows(): void
    {
        // eval 生成 105 层链式类 DepthChain0 -> DepthChain1 -> ... -> DepthChain104
        if (! class_exists('DepthChain0', false)) {
            for ($i = 0; $i <= 104; ++$i) {
                $next = 'DepthChain' . ($i + 1);
                eval('class DepthChain' . $i . ' { public ?' . $next . ' $next = null; }');
            }
        }

        $propertyManager = $this->makeManager();
        $this->expectException(DtoException::class);
        $this->expectExceptionMessage('nesting too deep');
        $propertyManager->scanClassProperties('DepthChain0');
    }

    public function testSelfReferencingClassDoesNotRecurseInfinitely(): void
    {
        $propertyManager = $this->makeManager();
        $propertyManager->scanClassProperties(DepthSelfRefDto::class);

        // scannedClasses 去重早退：不抛异常、属性被正确识别为类类型
        $property = $propertyManager->getProperty(DepthSelfRefDto::class, 'next');
        $this->assertSame(DepthSelfRefDto::class, $property->className);
        $this->assertFalse($property->isSimpleType);
    }

    public function testJsonFieldAliasWrittenToProperty(): void
    {
        (new JSONField('user_name'))->collectProperty(DepthAliasDto::class, 'name');

        $propertyManager = $this->makeManager();
        $property = $propertyManager->getProperty(DepthAliasDto::class, 'name');

        $this->assertSame('user_name', $property->alias);
    }

    public function testUnsupportedUnionDocblockFallsBackToReflectedType(): void
    {
        $propertyManager = $this->makeManager();
        $property = $propertyManager->getProperty(DepthUnionFirstWinsDto::class, 'list');

        // 不支持的联合类型不再阻断扫描，回退到反射类型 array
        $this->assertTrue($property->isSimpleType);
        $this->assertSame('array', $property->phpSimpleType);
        $this->assertNull($property->arrClassName);
    }

    public function testFailedScanIsRolledBack(): void
    {
        $propertyManager = $this->makeManager();
        $missingClass = 'HyperfTest\\DTO\\Scan\\MissingClassForRollbackTest';

        try {
            $propertyManager->scanClassProperties($missingClass);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $exception) {
            // expected
        }

        // 未回滚时会因「已扫描」直接返回；回滚后再次调用仍应抛出
        $this->expectException(\InvalidArgumentException::class);
        $propertyManager->scanClassProperties($missingClass);
    }

    public function testNullableUnionDocblockKeepsClassArrayType(): void
    {
        $propertyManager = $this->makeManager();
        $property = $propertyManager->getProperty(DepthUnionArrayDto::class, 'list');

        $this->assertSame(Address::class, $property->arrClassName);
        $this->assertFalse($property->isSimpleType);
        $this->assertTrue($property->isClassArray());
    }

    public function testDocblockVarWithDescriptionIsTruncatedAtSpace(): void
    {
        $propertyManager = $this->makeManager();
        $property = $propertyManager->getProperty(DepthDescribedArrayDto::class, 'ids');

        // @var int[] list of ids 按空格截断
        $this->assertSame('int', $property->arrSimpleType);
        $this->assertFalse($property->isSimpleType);
    }

    public function testSetterBackedArrayTypeAttributeIsScanned(): void
    {
        $propertyManager = $this->makeManager();
        $property = $propertyManager->getProperty(DepthSetterArrayTypeDto::class, 'items');

        $this->assertSame(Address::class, $property->arrClassName);
        $this->assertFalse($property->isSimpleType);
        $this->assertTrue($property->isClassArray());
    }

    public function testSetterBackedVarDocblockArrayIsScanned(): void
    {
        $propertyManager = $this->makeManager();
        $property = $propertyManager->getProperty(DepthSetterVarArrayDto::class, 'items');

        $this->assertSame(Address::class, $property->arrClassName);
        $this->assertTrue($property->isClassArray());
    }

    public function testVariadicClassSetterIsScannedAsClassArray(): void
    {
        $propertyManager = $this->makeManager();
        $property = $propertyManager->getProperty(DepthVariadicParentDto::class, 'children');

        $this->assertSame(DepthVariadicChild::class, $property->arrClassName);
        $this->assertTrue($property->isClassArray());
        $this->assertSame(1, $property->arrayDepth);
    }

    public function testEnumPropertyCollected(): void
    {
        $propertyManager = $this->makeManager();
        $property = $propertyManager->getProperty(DepthEnumDto::class, 'status');

        $this->assertNotNull($property->enum);
        $this->assertFalse($property->isSimpleType);
        $this->assertSame('string', $property->enum->backedType);
    }

    public function testGetPropertyAndNotSimpleTypeReturnsEmptyForSimpleOnlyClass(): void
    {
        $propertyManager = $this->makeManager();
        $propertyManager->scanClassProperties(DepthSimpleOnlyDto::class);

        $this->assertSame([], $propertyManager->getPropertyAndNotSimpleType(DepthSimpleOnlyDto::class));
    }

    private function makeManager(): PropertyManager
    {
        return new PropertyManager(new DtoCommon(), new PropertyEnum());
    }
}

class DepthSelfRefDto
{
    public ?DepthSelfRefDto $next = null;
}

class DepthAliasDto
{
    public string $name = '';
}

class DepthUnionArrayDto
{
    /**
     * @var null|Address[]
     */
    public array $list = [];
}

class DepthUnionFirstWinsDto
{
    /**
     * @var Address[]|int[]
     */
    public array $list = [];
}

class DepthDescribedArrayDto
{
    /**
     * @var int[] list of ids
     */
    public array $ids = [];
}

class DepthSetterArrayTypeDto
{
    #[ArrayType(Address::class)]
    private array $items = [];

    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function getItems(): array
    {
        return $this->items;
    }
}

class DepthSetterVarArrayDto
{
    /** @var Address[] */
    private array $items = [];

    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function getItems(): array
    {
        return $this->items;
    }
}

class DepthVariadicChild
{
    public string $name = '';
}

class DepthVariadicParentDto
{
    private array $children = [];

    public function setChildren(DepthVariadicChild ...$children): void
    {
        $this->children = $children;
    }

    public function getChildren(): array
    {
        return $this->children;
    }
}

enum DepthStatusEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

class DepthEnumDto
{
    public DepthStatusEnum $status = DepthStatusEnum::Active;
}

class DepthSimpleOnlyDto
{
    public string $name = '';

    public int $age = 0;
}
