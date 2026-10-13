<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Di\MethodDefinitionCollector;
use Hyperf\DTO\Aspect\CoreMiddlewareAspect;
use Hyperf\DTO\DtoCommon;
use Hyperf\DTO\DtoValidation;
use Hyperf\DTO\Scan\MethodParametersManager;
use Hyperf\DTO\Scan\PropertyEnum;
use Hyperf\DTO\Scan\PropertyManager;
use Hyperf\DTO\Scan\Scan;
use Hyperf\DTO\Scan\ValidationManager;
use Hyperf\HttpMessage\Server\Request;
use Hyperf\HttpMessage\Server\Response;
use HyperfTest\DTO\Controller\LazyScanController;
use HyperfTest\DTO\Request\LazyScanRequest;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;

/**
 * CoreMiddlewareAspect 懒扫描兜底：DTO 元数据缺失且未扫描过时按需补扫.
 *
 * 注意：MethodParametersManager 的元数据是静态存储、进程内共享，
 * 各用例使用不同的控制器方法隔离，避免相互污染.
 *
 * @internal
 * @coversNothing
 */
class CoreMiddlewareAspectLazyScanTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testScanMarker()
    {
        $manager = new MethodParametersManager();

        $this->assertFalse($manager->isMethodScanned(LazyScanController::class, 'create'));
        $this->assertNull($manager->getMethodParameter(LazyScanController::class, 'create', 'request'));

        $manager->setMethodParameters(LazyScanController::class, 'create');

        $this->assertTrue($manager->isMethodScanned(LazyScanController::class, 'create'));
        $parameter = $manager->getMethodParameter(LazyScanController::class, 'create', 'request');
        $this->assertNotNull($parameter);
        $this->assertTrue($parameter->isRequestBody());
        $this->assertFalse($parameter->isValid());

        // 无 DTO 注解的方法：只记录已扫描标记，不产生参数元数据
        $manager->setMethodParameters(LazyScanController::class, 'noAnnotation');
        $this->assertTrue($manager->isMethodScanned(LazyScanController::class, 'noAnnotation'));
        $this->assertNull($manager->getMethodParameter(LazyScanController::class, 'noAnnotation', 'id'));
    }

    public function testLazyScanMapsRequestBodyWhenMetadataMissing()
    {
        $manager = new MethodParametersManager();
        $this->assertFalse($manager->isMethodScanned(LazyScanController::class, 'update'));
        $this->assertNull($manager->getMethodParameter(LazyScanController::class, 'update', 'request'));

        [$aspect, $scan] = $this->makeAspect($manager, ['name' => '测试', 'age' => 18]);

        $result = $this->invokeValidateAndMap($aspect, 'update', new LazyScanRequest());

        // 懒扫描后 DTO 属性取到请求体的值
        $this->assertInstanceOf(LazyScanRequest::class, $result);
        $this->assertSame('测试', $result->name);
        $this->assertSame(18, $result->age);
        $this->assertSame(1, $scan->scanCount);
        $this->assertTrue($manager->isMethodScanned(LazyScanController::class, 'update'));
        $this->assertTrue($manager->getMethodParameter(LazyScanController::class, 'update', 'request')->isRequestBody());
    }

    public function testLazyScanRunsOnceForMethodWithoutAnnotations()
    {
        $manager = new MethodParametersManager();
        [$aspect, $scan] = $this->makeAspect($manager, ['name' => '不应映射', 'age' => 99]);

        $first = $this->invokeValidateAndMap($aspect, 'plain', new LazyScanRequest());
        $second = $this->invokeValidateAndMap($aspect, 'plain', new LazyScanRequest());

        // 无 DTO 注解：补扫一次后命中已扫描标记，原样返回默认 DTO，不重复扫描
        $this->assertSame('', $first->name);
        $this->assertSame('', $second->name);
        $this->assertSame(1, $scan->scanCount);
        $this->assertTrue($manager->isMethodScanned(LazyScanController::class, 'plain'));
    }

    /**
     * @return array{CoreMiddlewareAspect, Scan}
     */
    private function makeAspect(MethodParametersManager $manager, array $parsedBody): array
    {
        $container = m::mock(ContainerInterface::class);
        $scan = new class($container, new MethodDefinitionCollector(), new DtoCommon(), new PropertyManager(new DtoCommon(), new PropertyEnum()), new ValidationManager(), $manager) extends Scan {
            public int $scanCount = 0;

            public function scan(string $className, string $methodName): void
            {
                ++$this->scanCount;
                parent::scan($className, $methodName);
            }
        };
        $dtoValidation = m::mock(DtoValidation::class)->shouldIgnoreMissing();
        $container->shouldReceive('has')->andReturnUsing(static fn ($id) => class_exists($id) || interface_exists($id));
        $container->shouldReceive('get')->andReturnUsing(static fn ($id) => match ($id) {
            Scan::class => $scan,
            DtoValidation::class => $dtoValidation,
            default => null,
        });
        $container->shouldReceive('make')->andReturnUsing(static fn ($class) => new $class());
        ApplicationContext::setContainer($container);

        Context::set(ResponseInterface::class, new Response());
        Context::set(ServerRequestInterface::class, (new Request('POST', '/lazy_scan'))->withParsedBody($parsedBody));

        return [new CoreMiddlewareAspect($container, $manager), $scan];
    }

    private function invokeValidateAndMap(CoreMiddlewareAspect $aspect, string $method, LazyScanRequest $default): mixed
    {
        $reflection = new ReflectionMethod($aspect, 'validateAndMap');
        $reflection->setAccessible(true);
        return $reflection->invoke($aspect, LazyScanController::class . '::' . $method, 'request', LazyScanRequest::class, $default);
    }
}
