<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Aspect;

use Hyperf\Context\Context;
use Hyperf\Contract\Arrayable;
use Hyperf\Contract\Jsonable;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\DTO\Aspect\CoreMiddlewareAspect;
use Hyperf\DTO\Scan\MethodParametersManager;
use Hyperf\HttpMessage\Server\Response;
use Hyperf\HttpServer\CoreMiddleware;
use HyperfTest\DTO\AbstractTestCase;
use Mockery as m;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Swow\Psr7\Message\ResponsePlusInterface;

/**
 * CoreMiddlewareAspect::transferToResponse 全分支测试.
 *
 * @internal
 * @coversNothing
 */
class CoreMiddlewareAspectTransferTest extends AbstractTestCase
{
    public function testStringResponseReturnsTextPlain(): void
    {
        $result = $this->processTransfer('hello');
        $this->assertSame('text/plain', $result->getHeaderLine('content-type'));
        $this->assertSame('hello', (string) $result->getBody());
    }

    public function testResponseInterfaceReturnsProxy(): void
    {
        $response = new Response();
        $result = $this->processTransfer($response);
        $this->assertInstanceOf(ResponsePlusInterface::class, $result);
    }

    public function testArrayResponseReturnsJson(): void
    {
        $result = $this->processTransfer(['name' => 'dto']);
        $this->assertSame('application/json', $result->getHeaderLine('content-type'));
        $this->assertSame('{"name":"dto"}', (string) $result->getBody());
    }

    public function testArrayableResponseReturnsJson(): void
    {
        $arrayable = m::mock(Arrayable::class);
        $arrayable->shouldReceive('toArray')->andReturn(['id' => 1]);
        $arrayable->shouldReceive('jsonSerialize')->andReturn(['id' => 1]);

        $result = $this->processTransfer($arrayable);
        $this->assertSame('application/json', $result->getHeaderLine('content-type'));
        $this->assertSame('{"id":1}', (string) $result->getBody());
    }

    public function testJsonableResponseReturnsJson(): void
    {
        $jsonable = m::mock(Jsonable::class);
        $jsonable->shouldReceive('__toString')->andReturn('{"a":1}');

        $result = $this->processTransfer($jsonable);
        $this->assertSame('application/json', $result->getHeaderLine('content-type'));
        $this->assertSame('{"a":1}', (string) $result->getBody());
    }

    public function testObjectResponseReturnsJson(): void
    {
        $object = new \stdClass();
        $object->name = 'dto';

        $result = $this->processTransfer($object);
        $this->assertSame('application/json', $result->getHeaderLine('content-type'));
        $this->assertSame('{"name":"dto"}', (string) $result->getBody());
    }

    public function testScalarWithPresetContentTypeKeepsHeader(): void
    {
        // 已有 content-type 时不再覆盖，仅 (string) 强转输出
        $contextResponse = new Response();
        $contextResponse->addHeader('content-type', 'text/html');
        Context::set(ResponseInterface::class, $contextResponse);

        $result = $this->processTransferWithExistingContext(123);
        $this->assertSame('text/html', $result->getHeaderLine('content-type'));
        $this->assertSame('123', (string) $result->getBody());
    }

    public function testScalarWithoutContentTypeFallsBackToTextPlain(): void
    {
        $result = $this->processTransfer(123);
        $this->assertSame('text/plain', $result->getHeaderLine('content-type'));
        $this->assertSame('123', (string) $result->getBody());
    }

    public function testProcessRoutesOtherMethodsToOriginalPipeline(): void
    {
        $aspect = $this->makeAspect();
        $pjp = new ProceedingJoinPoint(
            fn () => null,
            CoreMiddleware::class,
            'otherMethod',
            ['keys' => []]
        );
        $pjp->pipe = fn ($p) => 'original_result';

        $this->assertSame('original_result', $aspect->process($pjp));
    }

    private function makeAspect(): CoreMiddlewareAspect
    {
        return new CoreMiddlewareAspect(
            m::mock(ContainerInterface::class),
            m::mock(MethodParametersManager::class)
        );
    }

    private function processTransfer(mixed $response): ResponseInterface
    {
        Context::set(ResponseInterface::class, new Response());
        return $this->processTransferWithExistingContext($response);
    }

    private function processTransferWithExistingContext(mixed $response): ResponseInterface
    {
        $pjp = new ProceedingJoinPoint(
            fn () => null,
            CoreMiddleware::class,
            'transferToResponse',
            ['keys' => ['response' => $response, 'request' => m::mock(ServerRequestInterface::class)]]
        );
        return $this->makeAspect()->process($pjp);
    }
}
