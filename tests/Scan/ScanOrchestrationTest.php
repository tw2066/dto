<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Scan;

use Hyperf\Di\MethodDefinitionCollector;
use Hyperf\DTO\Annotation\Validation\Required;
use Hyperf\DTO\DtoCommon;
use Hyperf\DTO\Scan\MethodParametersManager;
use Hyperf\DTO\Scan\PropertyEnum;
use Hyperf\DTO\Scan\PropertyManager;
use Hyperf\DTO\Scan\Scan;
use Hyperf\DTO\Scan\ValidationManager;
use HyperfTest\DTO\AbstractTestCase;
use Mockery as m;
use Psr\Container\ContainerInterface;

/**
 * Scan::scan 编排测试（参数类扫描 / 简单类型跳过 / 返回类型扫描 / 去重与清理）.
 *
 * @internal
 * @coversNothing
 */
class ScanOrchestrationTest extends AbstractTestCase
{
    public function testScanRegistersParametersAndScansDtoClasses(): void
    {
        (new Required())->collectProperty(ScanOrchDto::class, 'name');

        [$scan, $validationManager] = $this->makeScan([ScanOrchDto::class, ScanOrchResponseDto::class]);
        $scan->scan(ScanOrchController::class, 'handle');

        // DTO 参数类经容器 has=true → scanClass → 生成验证规则
        $data = $validationManager->getData(ScanOrchDto::class);
        $this->assertSame(['required'], $data['rule']['name']);

        // 简单类型 int 参数：container has('int')=false → 不触发扫描
        $content = $this->readPropertyManagerContent();
        $this->assertArrayNotHasKey('int', $content);

        // 返回类型类也被扫描（PropertyManager content 中有条目）
        $this->assertArrayHasKey(ScanOrchResponseDto::class, $content);
    }

    public function testScanGeneratesRulesForNestedDtoClasses(): void
    {
        (new Required())->collectProperty(ScanOrchNestedChildDto::class, 'name');
        (new Required())->collectProperty(ScanOrchNestedItemDto::class, 'value');

        [$scan, $validationManager] = $this->makeScan([ScanOrchNestedParentDto::class]);
        $scan->scan(ScanOrchNestedController::class, 'handle');

        $childData = $validationManager->getData(ScanOrchNestedChildDto::class);
        $this->assertSame(['required'], $childData['rule']['name'] ?? null);
        $itemData = $validationManager->getData(ScanOrchNestedItemDto::class);
        $this->assertSame(['required'], $itemData['rule']['value'] ?? null);
    }

    public function testScanIsIdempotent(): void
    {
        (new Required())->collectProperty(ScanOrchIdempotentDto::class, 'name');

        [$scan, $validationManager] = $this->makeScan([ScanOrchIdempotentDto::class]);
        $scan->scan(ScanOrchIdempotentController::class, 'handle');
        $first = $validationManager->getData(ScanOrchIdempotentDto::class);

        // 重复扫描同方法：setContent 跳过 / scanClassMethodParam 去重 / scanClass 去重
        $scan->scan(ScanOrchIdempotentController::class, 'handle');
        $second = $validationManager->getData(ScanOrchIdempotentDto::class);

        $this->assertSame($first, $second);
    }

    public function testRepeatedRouteScanGeneratesRulesOnce(): void
    {
        (new Required())->collectProperty(ScanOrchRepeatedDto::class, 'name');
        [$scan, $validationManager] = $this->makeScan([ScanOrchRepeatedDto::class]);

        $scan->scan(ScanOrchRepeatedController::class, 'handle');
        $scan->scan(ScanOrchRepeatedController::class, 'handle');

        $this->assertSame(1, $validationManager->generateCounts[ScanOrchRepeatedDto::class]['name']);
    }

    public function testClearScanClassArrayAllowsRescan(): void
    {
        (new Required())->collectProperty(ScanOrchClearDto::class, 'name');

        [$scan, $validationManager] = $this->makeScan([ScanOrchClearDto::class]);
        $scan->scanClass(ScanOrchClearDto::class);
        $scan->clearScanClassArray();
        // 清空后可重新扫描，无异常且数据仍在
        $scan->scanClass(ScanOrchClearDto::class);

        $data = $validationManager->getData(ScanOrchClearDto::class);
        $this->assertSame(['required'], $data['rule']['name']);
    }

    /**
     * @param string[] $containerHasClasses 容器 has() 返回 true 的类名
     */
    private function makeScan(array $containerHasClasses): array
    {
        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('has')->withAnyArgs()->andReturn(false)->byDefault();
        foreach ($containerHasClasses as $class) {
            $container->shouldReceive('has')->with($class)->andReturn(true)->byDefault();
        }

        $dtoCommon = new DtoCommon();
        $propertyEnum = new PropertyEnum();
        $propertyManager = new PropertyManager($dtoCommon, $propertyEnum);
        $validationManager = new CountingValidationManager();
        $methodParametersManager = new MethodParametersManager($dtoCommon, $propertyEnum, $propertyManager);

        $scan = new Scan(
            $container,
            new MethodDefinitionCollector(),
            $dtoCommon,
            $propertyManager,
            $validationManager,
            $methodParametersManager
        );
        return [$scan, $validationManager];
    }

    private function readPropertyManagerContent(): array
    {
        $property = new \ReflectionProperty(PropertyManager::class, 'content');
        return $property->getValue();
    }
}

class ScanOrchDto
{
    public string $name = '';
}

class ScanOrchResponseDto
{
    public string $id = '';
}

class ScanOrchController
{
    public function handle(ScanOrchDto $dto, int $id): ScanOrchResponseDto
    {
        return new ScanOrchResponseDto();
    }
}

class ScanOrchIdempotentDto
{
    public string $name = '';
}

class ScanOrchIdempotentController
{
    public function handle(ScanOrchIdempotentDto $dto): void {}
}

class ScanOrchClearDto
{
    public string $name = '';
}

class ScanOrchNestedParentDto
{
    public ?ScanOrchNestedChildDto $child = null;

    /**
     * @var ScanOrchNestedItemDto[]
     */
    public array $items = [];
}

class ScanOrchNestedChildDto
{
    public string $name = '';
}

class ScanOrchNestedItemDto
{
    public string $value = '';
}

class ScanOrchNestedController
{
    public function handle(ScanOrchNestedParentDto $dto): void {}
}

class ScanOrchRepeatedDto
{
    public string $name = '';
}

class ScanOrchRepeatedController
{
    public function handle(ScanOrchRepeatedDto $dto): void {}
}

class CountingValidationManager extends ValidationManager
{
    public array $generateCounts = [];

    public function generateValidation(string $className, string $fieldName): void
    {
        $this->generateCounts[$className][$fieldName] = ($this->generateCounts[$className][$fieldName] ?? 0) + 1;
        parent::generateValidation($className, $fieldName);
    }
}
