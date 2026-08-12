<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Scan;

use Hyperf\DTO\DtoCommon;
use Hyperf\DTO\Scan\MethodParametersManager;
use Hyperf\DTO\Scan\PropertyEnum;
use Hyperf\DTO\Scan\PropertyManager;
use HyperfTest\DTO\Response\Activity;
use HyperfTest\DTO\Response\Activity as Event;
use PHPUnit\Framework\TestCase;

/**
 * 跨命名空间 docblock 类型解析测试.
 *
 * 锁定 phpdocumentor ContextFactory 解析 use 语句的能力：
 * @var/@param 中的短类名（含别名导入）必须解析为 use 导入的 FQCN，
 * 若退化为按当前命名空间拼接（\HyperfTest\DTO\Scan\Activity 不存在），
 * arrClassName 会变为 null，以下断言立即失败
 *
 * @internal
 * @coversNothing
 */
class CrossNamespaceScanTest extends TestCase
{
    public function testCrossNamespaceVar(): void
    {
        $propertyManager = new PropertyManager(new DtoCommon(), new PropertyEnum());

        // 跨命名空间 use 导入：@var Activity[]
        $property = $propertyManager->getProperty(CrossNamespaceDto::class, 'activities');
        $this->assertSame('array', $property->phpSimpleType);
        $this->assertFalse($property->isSimpleType);
        $this->assertSame(Activity::class, $property->arrClassName);
        $this->assertTrue($property->isClassArray());

        // 别名导入：use Activity as Event + @var Event[]
        $property = $propertyManager->getProperty(CrossNamespaceDto::class, 'events');
        $this->assertSame(Activity::class, $property->arrClassName);

        // 数组元素类已被递归扫描
        $property = $propertyManager->getProperty(Activity::class, 'id');
        $this->assertSame('string', $property->phpSimpleType);
    }

    public function testCrossNamespaceParam(): void
    {
        $manager = new MethodParametersManager(new DtoCommon(), new PropertyEnum(), new PropertyManager(new DtoCommon(), new PropertyEnum()));

        // 跨命名空间 use 导入：@param Activity[] $activities
        $manager->scanClassMethodParam(CrossNamespaceController::class, 'batch');
        $property = $manager->getProperty(CrossNamespaceController::class, 'batch', 'activities');
        $this->assertSame('array', $property->phpSimpleType);
        $this->assertFalse($property->isSimpleType);
        $this->assertSame(Activity::class, $property->arrClassName);
        $this->assertTrue($property->isClassArray());

        // 别名导入：@param Event[] $events
        $manager->scanClassMethodParam(CrossNamespaceController::class, 'batchAlias');
        $property = $manager->getProperty(CrossNamespaceController::class, 'batchAlias', 'events');
        $this->assertSame(Activity::class, $property->arrClassName);
    }
}

class CrossNamespaceDto
{
    /**
     * @var Activity[]
     */
    public array $activities;

    /**
     * @var Event[]
     */
    public array $events;
}

class CrossNamespaceController
{
    /**
     * @param Activity[] $activities
     */
    public function batch(array $activities): void {}

    /**
     * @param Event[] $events
     */
    public function batchAlias(array $events): void {}
}
