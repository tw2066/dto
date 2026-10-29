<?php

declare(strict_types=1);

namespace Hyperf\DTO\Aspect;

use Hyperf\Codec\Json;
use Hyperf\Context\Context;
use Hyperf\Contract\Arrayable;
use Hyperf\Contract\Jsonable;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\ReflectionType;
use Hyperf\DTO\DtoValidation;
use Hyperf\DTO\Exception\DtoException;
use Hyperf\DTO\Mapper;
use Hyperf\DTO\Mapping\MappingContext;
use Hyperf\DTO\Scan\MethodParametersManager;
use Hyperf\DTO\Scan\Scan;
use Hyperf\HttpMessage\Server\ResponsePlusProxy;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\CoreMiddleware;
use InvalidArgumentException;
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

    public function __construct(private ContainerInterface $container, protected MethodParametersManager $methodParametersManager)
    {
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
     * @param null|array|Arrayable|Jsonable|ResponseInterface|string $response
     */
    protected function transferToResponse($response, ServerRequestInterface $request): ResponsePlusInterface
    {
        if (is_string($response)) {
            return $this->response()->addHeader('content-type', 'text/plain')->setBody(new SwooleStream($response));
        }

        if ($response instanceof ResponseInterface) {
            return new ResponsePlusProxy($response);
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

    /**
     * @param string $callableName 'App\Controller\DemoController::index'
     * @param mixed $obj
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function validateAndMap(string $callableName, string $paramName, string $className, $obj = null): mixed
    {
        [$controllerName, $methodName] = explode('::', $callableName);
        $methodParameter = $this->methodParametersManager->getMethodParameter($controllerName, $methodName, $paramName);
        if ($methodParameter == null && ! $this->methodParametersManager->isMethodScanned($controllerName, $methodName)) {
            // 元数据缺失时按需懒扫描（如 PHPUnit 测试下 BeforeServerListener 不会触发）
            $this->container->get(Scan::class)->scan($controllerName, $methodName);
            $methodParameter = $this->methodParametersManager->getMethodParameter($controllerName, $methodName, $paramName);
        }
        if ($methodParameter == null) {
            return $obj ?? $this->container->get($className);
        }
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
        if ($methodParameter->isValid()) {
            $this->container->get(DtoValidation::class)->validate($className, $param);
        }
        return Mapper::mapWithContext($param, make($className), new MappingContext());
    }

    protected function arrayValidateAndMap(string $callableName, string $paramName): array
    {
        [$controllerName, $methodName] = explode('::', $callableName);
        $methodParameter = $this->methodParametersManager->getMethodParameter($controllerName, $methodName, $paramName);
        if (! $methodParameter?->isRequestBody()) {
            return [];
        }

        $property = $this->methodParametersManager->getProperty($controllerName, $methodName, $paramName);
        /** @var ServerRequestInterface $request */
        $request = Context::get(ServerRequestInterface::class);
        $params = $request->getParsedBody();
        if (empty($params)) {
            return [];
        }
        if ($property?->arrClassName == null) {
            if ($property?->arrSimpleType !== null || ! $methodParameter->isValid()) {
                return $params;
            }
            throw new DtoException("Parameter [{$callableName}:{$paramName}] uses #[Valid], but the array element type cannot be resolved.");
        }

        $context = new MappingContext();
        $validation = $methodParameter->isValid() ? $this->container->get(DtoValidation::class) : null;
        $data = $this->mapArrayParameterItems(
            $property->arrClassName,
            max(1, $property->arrayDepth),
            $params,
            $validation,
            $context
        );
        return array_values($data);
    }

    private function mapArrayParameterItems(
        string $className,
        int $remainingDepth,
        array|object $value,
        ?DtoValidation $validation,
        MappingContext $context,
    ): array {
        $result = [];
        foreach ($value as $key => $item) {
            if ($item === null) {
                $result[$key] = null;
                continue;
            }
            if ($remainingDepth > 1) {
                $result[$key] = is_array($item) || is_object($item)
                    ? $this->mapArrayParameterItems($className, $remainingDepth - 1, $item, $validation, $context)
                    : $item;
                continue;
            }
            if ($validation !== null) {
                $validation->validate($className, $item);
            }
            $result[$key] = Mapper::mapWithContext($item, make($className), $context);
        }
        return $result;
    }

    /**
     * @param ReflectionType[] $definitions
     * @param mixed $coreMiddleware
     */
    private function getInjections(array $definitions, string $callableName, array $arguments, $coreMiddleware): array
    {
        $injections = [];
        foreach ($definitions as $pos => $definition) {
            $value = $arguments[$pos] ?? $arguments[$definition->getMeta('name')] ?? null;
            if ($value === null) {
                if ($definition->getMeta('defaultValueAvailable')) {
                    $injections[] = $definition->getMeta('defaultValue');
                } elseif ($this->container->has($definition->getName())) {
                    // 参数注解存在时只创建映射目标，避免先 get 后 make 重复实例化。
                    $injections[] = $this->validateAndMap($callableName, $definition->getMeta('name'), $definition->getName());
                } elseif ($definition->getName() === 'array') {
                    // 修改 支持json数组
                    $injections[] = $this->arrayValidateAndMap($callableName, $definition->getMeta('name'));
                } elseif ($definition->allowsNull()) {
                    $injections[] = null;
                } else {
                    throw new InvalidArgumentException("Parameter '{$definition->getMeta('name')}' "
                        . "of {$callableName} should not be null");
                }
            } else {
                // RPC 参数统一走 Symfony denormalize；带 #[Valid] 的仍先验证。
                [$controllerName, $methodName] = explode('::', $callableName);
                $className = $definition->getName();
                $methodParameter = $this->methodParametersManager->getMethodParameter($controllerName, $methodName, $definition->getMeta('name'));
                if ($methodParameter?->isValid()) {
                    $this->container->get(DtoValidation::class)->validate($className, $value);
                }
                $injections[] = $coreMiddleware->getNormalizer()->denormalize($value, $className);
            }
        }
        return $injections;
    }
}
