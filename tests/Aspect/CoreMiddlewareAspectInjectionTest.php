<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Aspect;

use Hyperf\Context\Context;
use Hyperf\Contract\NormalizerInterface;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\ReflectionType;
use Hyperf\DTO\Annotation\Contracts\RequestBody;
use Hyperf\DTO\Annotation\Contracts\RequestFormData;
use Hyperf\DTO\Annotation\Contracts\RequestHeader;
use Hyperf\DTO\Annotation\Contracts\RequestQuery;
use Hyperf\DTO\Annotation\Contracts\Valid;
use Hyperf\DTO\Aspect\CoreMiddlewareAspect;
use Hyperf\DTO\DtoCommon;
use Hyperf\DTO\DtoValidation;
use Hyperf\DTO\Scan\MethodParametersManager;
use Hyperf\DTO\Scan\PropertyEnum;
use Hyperf\DTO\Scan\PropertyManager;
use Hyperf\HttpServer\CoreMiddleware;
use HyperfTest\DTO\AbstractTestCase;
use Mockery as m;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * CoreMiddlewareAspect::getInjections 全分支测试（HTTP 注入 + RPC 注入）.
 *
 * @internal
 * @coversNothing
 */
class CoreMiddlewareAspectInjectionTest extends AbstractTestCase
{
    // ---------------- HTTP 路径（$value === null） ----------------

    public function testDefaultValueUsedWhenArgumentMissing(): void
    {
        // A1: 有默认值时直接注入默认值，容器不参与
        $container = m::mock(ContainerInterface::class);
        $aspect = new CoreMiddlewareAspect($container, $this->makeManager());

        $definitions = [new ReflectionType('int', false, [
            'name' => 'count', 'defaultValueAvailable' => true, 'defaultValue' => 42,
        ])];
        $injections = $aspect->process($this->makePjp(null, $definitions, 'any', []));

        $this->assertSame([42], $injections);
    }

    public function testRequestBodyWithValidMapsDto(): void
    {
        // A2 + RequestBody + Valid: 取 getParsedBody → 验证 → 映射
        $manager = $this->makeManager();
        $manager->setMethodParameters(AspectBodyController::class, 'handle');

        $body = ['name' => 'dto', 'age' => 18];
        $request = m::mock(ServerRequestInterface::class);
        $request->shouldReceive('getParsedBody')->andReturn($body);
        Context::set(ServerRequestInterface::class, $request);

        $dtoValidation = m::mock(DtoValidation::class);
        $dtoValidation->shouldReceive('validate')->once()->with(AspectInjectDto::class, $body);

        $container = $this->makeContainer($dtoValidation, AspectInjectDto::class);
        $aspect = new CoreMiddlewareAspect($container, $manager);

        $injections = $aspect->process($this->makePjp(
            null,
            [$this->makeDefinition(AspectInjectDto::class, 'req')],
            AspectBodyController::class . '::handle',
            []
        ));

        $this->assertInstanceOf(AspectInjectDto::class, $injections[0]);
        $this->assertSame('dto', $injections[0]->name);
        $this->assertSame(18, $injections[0]->age);
    }

    public function testRequestQueryUsesQueryParams(): void
    {
        $manager = $this->makeManager();
        $manager->setMethodParameters(AspectQueryController::class, 'handle');

        $query = ['name' => 'query', 'age' => 20];
        $request = m::mock(ServerRequestInterface::class);
        $request->shouldReceive('getQueryParams')->andReturn($query);
        Context::set(ServerRequestInterface::class, $request);

        $dtoValidation = m::mock(DtoValidation::class);
        $dtoValidation->shouldReceive('validate')->once()->with(AspectInjectDto::class, $query);

        $aspect = new CoreMiddlewareAspect($this->makeContainer($dtoValidation, AspectInjectDto::class), $manager);
        $injections = $aspect->process($this->makePjp(
            null,
            [$this->makeDefinition(AspectInjectDto::class, 'req')],
            AspectQueryController::class . '::handle',
            []
        ));

        $this->assertSame('query', $injections[0]->name);
    }

    public function testRequestFormDataUsesParsedBody(): void
    {
        $manager = $this->makeManager();
        $manager->setMethodParameters(AspectFormController::class, 'handle');

        $form = ['name' => 'form', 'age' => 30];
        $request = m::mock(ServerRequestInterface::class);
        $request->shouldReceive('getParsedBody')->andReturn($form);
        Context::set(ServerRequestInterface::class, $request);

        $dtoValidation = m::mock(DtoValidation::class);
        $dtoValidation->shouldReceive('validate')->once()->with(AspectInjectDto::class, $form);

        $aspect = new CoreMiddlewareAspect($this->makeContainer($dtoValidation, AspectInjectDto::class), $manager);
        $injections = $aspect->process($this->makePjp(
            null,
            [$this->makeDefinition(AspectInjectDto::class, 'req')],
            AspectFormController::class . '::handle',
            []
        ));

        $this->assertSame('form', $injections[0]->name);
    }

    public function testRequestHeaderTakesFirstValueOfEachHeader(): void
    {
        $manager = $this->makeManager();
        $manager->setMethodParameters(AspectHeaderController::class, 'handle');

        $request = m::mock(ServerRequestInterface::class);
        $request->shouldReceive('getHeaders')->andReturn(['name' => ['john', 'second'], 'age' => ['5']]);
        Context::set(ServerRequestInterface::class, $request);

        $dtoValidation = m::mock(DtoValidation::class);
        // 多值头被截断为首值
        $dtoValidation->shouldReceive('validate')->once()->with(AspectInjectDto::class, ['name' => 'john', 'age' => '5']);

        $aspect = new CoreMiddlewareAspect($this->makeContainer($dtoValidation, AspectInjectDto::class), $manager);
        $injections = $aspect->process($this->makePjp(
            null,
            [$this->makeDefinition(AspectInjectDto::class, 'req')],
            AspectHeaderController::class . '::handle',
            []
        ));

        $this->assertSame('john', $injections[0]->name);
    }

    public function testOnlyValidAnnotationValidatesEmptyArray(): void
    {
        // 仅 #[Valid] 无来源注解: $param = []，仍执行验证
        $manager = $this->makeManager();
        $manager->setMethodParameters(AspectOnlyValidController::class, 'handle');

        Context::set(ServerRequestInterface::class, m::mock(ServerRequestInterface::class));

        $dtoValidation = m::mock(DtoValidation::class);
        $dtoValidation->shouldReceive('validate')->once()->with(AspectInjectDto::class, []);

        $aspect = new CoreMiddlewareAspect($this->makeContainer($dtoValidation, AspectInjectDto::class), $manager);
        $injections = $aspect->process($this->makePjp(
            null,
            [$this->makeDefinition(AspectInjectDto::class, 'req')],
            AspectOnlyValidController::class . '::handle',
            []
        ));

        $this->assertInstanceOf(AspectInjectDto::class, $injections[0]);
    }

    public function testNoAnnotationReturnsContainerInstanceAsIs(): void
    {
        // 参数无契约注解: methodParameter 为 null，容器实例原样返回，不验证不映射
        $manager = $this->makeManager();
        $manager->setMethodParameters(AspectPlainController::class, 'handle');

        $dtoValidation = m::mock(DtoValidation::class);
        $dtoValidation->shouldNotReceive('validate');

        $obj = new AspectInjectDto();
        $container = $this->makeContainer($dtoValidation, AspectInjectDto::class, $obj);
        $aspect = new CoreMiddlewareAspect($container, $manager);

        $injections = $aspect->process($this->makePjp(
            null,
            [$this->makeDefinition(AspectInjectDto::class, 'req')],
            AspectPlainController::class . '::handle',
            []
        ));

        $this->assertSame($obj, $injections[0]);
    }

    public function testArrayOfDtoMappedAndValidatedPerItem(): void
    {
        // A3: array 参数 + @param Dto[] + RequestBody + Valid → 逐元素验证映射
        $manager = $this->makeManager();
        $manager->setMethodParameters(AspectArrayController::class, 'handle');
        $manager->scanClassMethodParam(AspectArrayController::class, 'handle');

        $body = [['name' => 'a', 'age' => 1], ['name' => 'b', 'age' => 2]];
        $request = m::mock(ServerRequestInterface::class);
        $request->shouldReceive('getParsedBody')->andReturn($body);
        Context::set(ServerRequestInterface::class, $request);

        $dtoValidation = m::mock(DtoValidation::class);
        $dtoValidation->shouldReceive('validate')->once()->with(AspectInjectDto::class, $body[0]);
        $dtoValidation->shouldReceive('validate')->once()->with(AspectInjectDto::class, $body[1]);

        $aspect = new CoreMiddlewareAspect($this->makeContainer($dtoValidation), $manager);
        $injections = $aspect->process($this->makePjp(
            null,
            [$this->makeDefinition('array', 'items')],
            AspectArrayController::class . '::handle',
            []
        ));

        $this->assertCount(2, $injections[0]);
        $this->assertInstanceOf(AspectInjectDto::class, $injections[0][0]);
        $this->assertSame('a', $injections[0][0]->name);
        $this->assertSame('b', $injections[0][1]->name);
    }

    public function testArrayParamWithoutRequestBodyReturnsEmptyArray(): void
    {
        // A3 但非 RequestBody（如 RequestQuery）: 静默返回 []
        $manager = $this->makeManager();
        $manager->setMethodParameters(AspectArrayQueryController::class, 'handle');
        $manager->scanClassMethodParam(AspectArrayQueryController::class, 'handle');

        $dtoValidation = m::mock(DtoValidation::class);
        $dtoValidation->shouldNotReceive('validate');

        $aspect = new CoreMiddlewareAspect($this->makeContainer($dtoValidation), $manager);
        $injections = $aspect->process($this->makePjp(
            null,
            [$this->makeDefinition('array', 'items')],
            AspectArrayQueryController::class . '::handle',
            []
        ));

        $this->assertSame([], $injections[0]);
    }

    public function testArrayParamWithoutDocblockReturnsRawBody(): void
    {
        // A3 无 @param docblock: arrClassName 为 null，原样返回 parsedBody
        $manager = $this->makeManager();
        $manager->setMethodParameters(AspectArrayNoDocController::class, 'handle');
        $manager->scanClassMethodParam(AspectArrayNoDocController::class, 'handle');

        $body = [['name' => 'raw']];
        $request = m::mock(ServerRequestInterface::class);
        $request->shouldReceive('getParsedBody')->andReturn($body);
        Context::set(ServerRequestInterface::class, $request);

        $dtoValidation = m::mock(DtoValidation::class);
        $aspect = new CoreMiddlewareAspect($this->makeContainer($dtoValidation), $manager);
        $injections = $aspect->process($this->makePjp(
            null,
            [$this->makeDefinition('array', 'items')],
            AspectArrayNoDocController::class . '::handle',
            []
        ));

        $this->assertSame($body, $injections[0]);
    }

    public function testArrayParamWithEmptyBodyReturnsEmptyArray(): void
    {
        $manager = $this->makeManager();
        $manager->setMethodParameters(AspectArrayController::class, 'handle');
        $manager->scanClassMethodParam(AspectArrayController::class, 'handle');

        $request = m::mock(ServerRequestInterface::class);
        $request->shouldReceive('getParsedBody')->andReturn([]);
        Context::set(ServerRequestInterface::class, $request);

        $dtoValidation = m::mock(DtoValidation::class);
        $dtoValidation->shouldNotReceive('validate');

        $aspect = new CoreMiddlewareAspect($this->makeContainer($dtoValidation), $manager);
        $injections = $aspect->process($this->makePjp(
            null,
            [$this->makeDefinition('array', 'items')],
            AspectArrayController::class . '::handle',
            []
        ));

        $this->assertSame([], $injections[0]);
    }

    public function testNullableParameterInjectsNull(): void
    {
        // A4: 容器无此类型且允许 null → 注入 null
        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('has')->withAnyArgs()->andReturn(false);
        $aspect = new CoreMiddlewareAspect($container, $this->makeManager());

        $definitions = [new ReflectionType('int', true, [
            'name' => 'count', 'defaultValueAvailable' => false,
        ])];
        $injections = $aspect->process($this->makePjp(null, $definitions, 'any', []));

        $this->assertSame([null], $injections);
    }

    public function testUnresolvableParameterThrows(): void
    {
        // A5: 无默认值、容器无、非 array、不允许 null → 抛异常
        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('has')->withAnyArgs()->andReturn(false);
        $aspect = new CoreMiddlewareAspect($container, $this->makeManager());

        $definitions = [new ReflectionType('int', false, [
            'name' => 'count', 'defaultValueAvailable' => false,
        ])];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Parameter 'count' of any should not be null");
        $aspect->process($this->makePjp(null, $definitions, 'any', []));
    }

    // ---------------- RPC 路径（$value !== null） ----------------

    public function testRpcValidParameterValidatesThenDenormalizes(): void
    {
        $manager = $this->makeManager();
        $manager->setMethodParameters(AspectRpcValidController::class, 'handle');

        $value = ['name' => 'rpc', 'age' => 1];
        $dtoValidation = m::mock(DtoValidation::class);
        $dtoValidation->shouldReceive('validate')->once()->with(AspectInjectDto::class, $value);

        $dto = new AspectInjectDto();
        $normalizer = m::mock(NormalizerInterface::class);
        $normalizer->shouldReceive('denormalize')->once()->with($value, AspectInjectDto::class)->andReturn($dto);
        $coreMiddleware = m::mock(CoreMiddleware::class);
        $coreMiddleware->shouldReceive('getNormalizer')->andReturn($normalizer);

        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with(DtoValidation::class)->andReturn($dtoValidation);

        $aspect = new CoreMiddlewareAspect($container, $manager);
        $injections = $aspect->process($this->makePjp(
            $coreMiddleware,
            [$this->makeDefinition(AspectInjectDto::class, 'req')],
            AspectRpcValidController::class . '::handle',
            [0 => $value]
        ));

        $this->assertSame($dto, $injections[0]);
    }

    public function testRpcNonValidParameterOnlyDenormalizes(): void
    {
        $manager = $this->makeManager();
        $manager->setMethodParameters(AspectPlainController::class, 'handle');

        $value = ['name' => 'rpc'];
        $dtoValidation = m::mock(DtoValidation::class);
        $dtoValidation->shouldNotReceive('validate');

        $dto = new AspectInjectDto();
        $normalizer = m::mock(NormalizerInterface::class);
        $normalizer->shouldReceive('denormalize')->once()->andReturn($dto);
        $coreMiddleware = m::mock(CoreMiddleware::class);
        $coreMiddleware->shouldReceive('getNormalizer')->andReturn($normalizer);

        $aspect = new CoreMiddlewareAspect(m::mock(ContainerInterface::class), $manager);
        $injections = $aspect->process($this->makePjp(
            $coreMiddleware,
            [$this->makeDefinition(AspectInjectDto::class, 'req')],
            AspectPlainController::class . '::handle',
            [0 => $value]
        ));

        $this->assertSame($dto, $injections[0]);
    }

    public function testRpcArgumentKeyedByParameterName(): void
    {
        // arguments 按参数名 keyed 时也能取到值
        $manager = $this->makeManager();
        $manager->setMethodParameters(AspectRpcValidController::class, 'handle');

        $value = ['name' => 'rpc'];
        $dtoValidation = m::mock(DtoValidation::class);
        $dtoValidation->shouldReceive('validate')->once()->with(AspectInjectDto::class, $value);

        $dto = new AspectInjectDto();
        $normalizer = m::mock(NormalizerInterface::class);
        $normalizer->shouldReceive('denormalize')->once()->with($value, AspectInjectDto::class)->andReturn($dto);
        $coreMiddleware = m::mock(CoreMiddleware::class);
        $coreMiddleware->shouldReceive('getNormalizer')->andReturn($normalizer);

        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with(DtoValidation::class)->andReturn($dtoValidation);

        $aspect = new CoreMiddlewareAspect($container, $manager);
        $injections = $aspect->process($this->makePjp(
            $coreMiddleware,
            [$this->makeDefinition(AspectInjectDto::class, 'req')],
            AspectRpcValidController::class . '::handle',
            ['req' => $value]
        ));

        $this->assertSame($dto, $injections[0]);
    }

    // ---------------- 工具方法 ----------------

    private function makeManager(): MethodParametersManager
    {
        $dtoCommon = new DtoCommon();
        $propertyEnum = new PropertyEnum();
        $propertyManager = new PropertyManager($dtoCommon, $propertyEnum);
        return new MethodParametersManager($dtoCommon, $propertyEnum, $propertyManager);
    }

    private function makeDefinition(string $typeName, string $paramName): ReflectionType
    {
        return new ReflectionType($typeName, false, [
            'name' => $paramName, 'defaultValueAvailable' => false,
        ]);
    }

    /**
     * @param null|string $injectClass 容器 has() 返回 true 的类型名（array 参数场景传 null，容器本就无 'array'）
     * @param mixed $dtoValidation
     */
    private function makeContainer($dtoValidation, ?string $injectClass = null, ?object $injectObj = null): ContainerInterface
    {
        $container = m::mock(ContainerInterface::class);
        // 注意: Mockery 后声明的期望优先匹配，withAnyArgs 兜底必须先声明
        $container->shouldReceive('has')->withAnyArgs()->andReturn(false)->byDefault();
        $container->shouldReceive('get')->with(DtoValidation::class)->andReturn($dtoValidation)->byDefault();
        if ($injectClass !== null) {
            $container->shouldReceive('has')->with($injectClass)->andReturn(true)->byDefault();
            $container->shouldReceive('get')->with($injectClass)->andReturn($injectObj ?? new AspectInjectDto())->byDefault();
        }
        return $container;
    }

    private function makePjp(?CoreMiddleware $coreMiddleware, array $definitions, string $callableName, array $arguments): ProceedingJoinPoint
    {
        $closure = $coreMiddleware === null
            ? fn () => null
            : \Closure::bind(fn () => null, $coreMiddleware);
        return new ProceedingJoinPoint($closure, CoreMiddleware::class, 'getInjections', [
            'keys' => [
                'definitions' => $definitions,
                'callableName' => $callableName,
                'arguments' => $arguments,
            ],
        ]);
    }
}

class AspectInjectDto
{
    public string $name = '';

    public int $age = 0;
}

class AspectBodyController
{
    public function handle(#[RequestBody] #[Valid] AspectInjectDto $req) {}
}

class AspectQueryController
{
    public function handle(#[RequestQuery] #[Valid] AspectInjectDto $req) {}
}

class AspectFormController
{
    public function handle(#[RequestFormData] #[Valid] AspectInjectDto $req) {}
}

class AspectHeaderController
{
    public function handle(#[RequestHeader] #[Valid] AspectInjectDto $req) {}
}

class AspectOnlyValidController
{
    public function handle(#[Valid] AspectInjectDto $req) {}
}

class AspectPlainController
{
    public function handle(AspectInjectDto $req) {}
}

class AspectArrayController
{
    /**
     * @param AspectInjectDto[] $items
     */
    public function handle(#[RequestBody] #[Valid] array $items) {}
}

class AspectArrayQueryController
{
    /**
     * @param AspectInjectDto[] $items
     */
    public function handle(#[RequestQuery] array $items) {}
}

class AspectArrayNoDocController
{
    public function handle(#[RequestBody] array $items) {}
}

class AspectRpcValidController
{
    public function handle(#[Valid] AspectInjectDto $req) {}
}
