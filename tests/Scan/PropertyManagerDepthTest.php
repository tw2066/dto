<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Scan;

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

    public function testUnionDocblockVarIsTruncatedToFirstType(): void
    {
        $propertyManager = $this->makeManager();
        $property = $propertyManager->getProperty(DepthUnionFirstWinsDto::class, 'list');

        // @var Address[]|int[] 截断为 Address[]
        $this->assertSame(Address::class, $property->arrClassName);
        $this->assertFalse($property->isSimpleType);
    }

    public function testUnionDocblockVarWithNullFirstLosesClassArrayType(): void
    {
        // src 现状（坑）: 联合类型简单按 | 截断取第一个，null|Address[] 截出 'null'，
        // 类数组类型信息丢失（此用例锁定该行为，修复后应翻转）
        $propertyManager = $this->makeManager();
        $property = $propertyManager->getProperty(DepthUnionArrayDto::class, 'list');

        $this->assertNull($property->arrClassName);
    }

    public function testDocblockVarWithDescriptionIsTruncatedAtSpace(): void
    {
        $propertyManager = $this->makeManager();
        $property = $propertyManager->getProperty(DepthDescribedArrayDto::class, 'ids');

        // @var int[] list of ids 按空格截断
        $this->assertSame('int', $property->arrSimpleType);
        $this->assertFalse($property->isSimpleType);
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
