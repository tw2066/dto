<?php

declare(strict_types=1);

namespace Hyperf\DTO\Aspect;

use Hyperf\Codec\Json;
use Hyperf\Context\Context;
use Hyperf\Contract\Arrayable;
use Hyperf\Contract\Jsonable;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\DTO\Mapper;
use Hyperf\DTO\Scan\MethodParametersManager;
use Hyperf\DTO\ValidationDto;
use Hyperf\HttpMessage\Server\ResponsePlusProxy;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\CoreMiddleware;
use Hyperf\Stringable\Str;
use Hyperf\Support\Composer;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Swow\Psr7\Message\ResponsePlusInterface;
use function Hyperf\Support\make;

class CoreMiddlewareAspect
{
    public array $classes = [
        CoreMiddleware::class . '::getInjections',
        CoreMiddleware::class . '::transferToResponse',
    ];

    protected int $hyperfVersion = 31;

    public function __construct(private ContainerInterface $container)
    {
        //hyperf/http-server version
        $version = Composer::getVersions()['hyperf/http-server'] ?? '';
        if (Str::startsWith($version, 'v3.0.')) {
            $this->hyperfVersion = 30;
        }
    }

    /**
     * @return mixed
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        /* @var CoreMiddleware $coreMiddleware */
        $coreMiddleware = $proceedingJoinPoint->getInstance();
        if ($proceedingJoinPoint->methodName === 'transferToResponse') {
            $response = $proceedingJoinPoint->arguments['keys']['response'];
            $request = $proceedingJoinPoint->arguments['keys']['request'];
            if ($this->hyperfVersion === 30) {
                return $this->transferToResponse30($response, $request);
            }
            return $this->transferToResponse($response, $request);
        }

        if ($proceedingJoinPoint->methodName === 'getInjections') {
            $definitions = $proceedingJoinPoint->arguments['keys']['definitions'];
            $callableName = $proceedingJoinPoint->arguments['keys']['callableName'];
            $arguments = $proceedingJoinPoint->arguments['keys']['arguments'];
            return $this->getInjections($definitions, $callableName, $arguments, $coreMiddleware);
        }
        return $proceedingJoinPoint->process();
    }

    /**
     * Get response instance from context.
     */
    protected function response(): ResponseInterface
    {
        return Context::get(ResponseInterface::class);
    }

    /**
     * Transfer the non-standard response content to a standard response object.
     *
     * @param null|array|Arrayable|Jsonable|string $response
     */
    protected function transferToResponse30($response, ServerRequestInterface $request): ResponseInterface
    {
        if (is_string($response)) {
            return $this->response()->withAddedHeader('content-type', 'text/plain')->withBody(new SwooleStream($response));
        }

        if (is_array($response) || $response instanceof Arrayable) {
            return $this->response()
                ->withAddedHeader('content-type', 'application/json')
                ->withBody(new SwooleStream(Json::encode($response)));
        }

        if ($response instanceof Jsonable) {
            return $this->response()
                ->withAddedHeader('content-type', 'application/json')
                ->withBody(new SwooleStream((string) $response));
        }
        // object
        if (is_object($response)) {
            return $this->response()
                ->withAddedHeader('content-type', 'application/json')
                ->withBody(new SwooleStream(Json::encode($response)));
        }

        if ($this->response()->hasHeader('content-type')) {
            return $this->response()->withBody(new SwooleStream((string) $response));
        }

        return $this->response()->withAddedHeader('content-type', 'text/plain')->withBody(new SwooleStream((string) $response));
    }

    /**
     * Transfer the non-standard response content to a standard response object.
     *
     * @param null|array|Arrayable|Jsonable|ResponseInterface|string $response
     */
    protected function transferToResponse($response, ServerRequestInterface $request): ResponsePlusInterface
    {
        if (is_string($response)) {
            return $this->response()->addHeader('content-type', 'text/plain')->setBody(new SwooleStream($response));
        }

        if (is_array($response) || $response instanceof Arrayable) {
            return $this->response()
                ->addHeader('content-type', 'application/json')
                ->setBody(new SwooleStream(Json::encode($response)));
        }

        if ($response instanceof Jsonable) {
            return $this->response()
                ->addHeader('content-type', 'application/json')
                ->setBody(new SwooleStream((string) $response));
        }

        if ($response instanceof ResponseInterface) {
            return new ResponsePlusProxy($response);
        }

        // object
        if (is_object($response)) {
            return $this->response()
                ->addHeader('content-type', 'application/json')
                ->setBody(new SwooleStream(Json::encode($response)));
        }

        if ($this->response()->hasHeader('content-type')) {
            return $this->response()->setBody(new SwooleStream((string) $response));
        }

        return $this->response()->addHeader('content-type', 'text/plain')->setBody(new SwooleStream((string) $response));
    }

    private function getInjections(array $definitions, string $callableName, array $arguments, $coreMiddleware): array
    {
        $injections = [];
        foreach ($definitions ?? [] as $pos => $definition) {
            $value = $arguments[$pos] ?? $arguments[$definition->getMeta('name')] ?? null;
            if ($value === null) {
                if ($definition->getMeta('defaultValueAvailable')) {
                    $injections[] = $definition->getMeta('defaultValue');
                } elseif ($definition->allowsNull()) {
                    $injections[] = null;
                } elseif ($this->container->has($definition->getName())) {
                    //修改
                    $obj = $this->container->get($definition->getName());
                    $injections[] = $this->validateAndMap($callableName, $definition->getMeta('name'), $definition->getName(), $obj);
                } else {
                    throw new \InvalidArgumentException("Parameter '{$definition->getMeta('name')}' "
                        . "of {$callableName} should not be null");
                }
            } else {
                //标记
                $injections[] = $coreMiddleware->getNormalizer()->denormalize($value, $definition->getName());
            }
        }
        return $injections;
    }

    /**
     * @param string $callableName 'App\Controller\DemoController::index'
     * @param mixed $obj
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function validateAndMap(string $callableName, string $paramName, string $className, $obj): mixed
    {
        [$controllerName, $methodName] = explode('::', $callableName);
        $methodParameter = MethodParametersManager::getMethodParameter($controllerName, $methodName, $paramName);
        if ($methodParameter == null) {
            return $obj;
        }
        $validationDTO = $this->container->get(ValidationDto::class);
        /** @var ServerRequestInterface $request */
        $request = Context::get(ServerRequestInterface::class);
        $param = [];
        if ($methodParameter->isRequestBody()) {
            $param = $request->getParsedBody();
        } elseif ($methodParameter->isRequestQuery()) {
            $param = $request->getQueryParams();
        } elseif ($methodParameter->isRequestFormData()) {
            $param = $request->getParsedBody();
        } elseif ($methodParameter->isRequestHeader()) {
            $param = array_map(function ($value) {
                return $value[0];
            }, $request->getHeaders());
        }
        // validate
        if ($methodParameter->isValid()) {
            $validationDTO->validate($className, $param);
        }
        return Mapper::map($param, make($className));
    }
}
