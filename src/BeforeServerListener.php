<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Closure;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DTO\Event\AfterDtoStart;
use Hyperf\DTO\Event\BeforeDtoStart;
use Hyperf\DTO\Router\JsonRpcHttpRouter;
use Hyperf\DTO\Router\JsonRpcTcpRouter;
use Hyperf\DTO\Scan\Scan;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeServerStart;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\HttpServer\Router\Handler;
use Hyperf\JsonRpc\HttpServer;
use Hyperf\JsonRpc\TcpServer;
use Hyperf\Server\Event\MainCoroutineServerStart;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Throwable;

use function Hyperf\Collection\collect;

class BeforeServerListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            BeforeDtoStart::class,
            BeforeServerStart::class,
            MainCoroutineServerStart::class,
        ];
    }

    public function process(object $event): void
    {
        if ($event instanceof BeforeServerStart) {
            $serverName = $event->serverName;
        } else {
            /** @var MainCoroutineServerStart $event */
            $serverName = $event->name;
        }

        $container = ApplicationContext::getContainer();
        $config = $container->get(ConfigInterface::class);
        $logger = $container->get(StdoutLoggerInterface::class);
        $eventDispatcher = $container->get(EventDispatcherInterface::class);
        $scanAnnotation = $container->get(Scan::class);
        $serverConfig = collect($config->get('server.servers'))->where('name', $serverName)->first();

        if (($serverConfig['callbacks']['receive'][0] ?? '') == TcpServer::class) {
            try {
                $tcpRouter = $container->get(JsonRpcTcpRouter::class);
                $router = $tcpRouter->getRouter($serverName);
            } catch (Throwable $throwable) {
                $logger->warning($throwable);
                return;
            }
        } elseif (($serverConfig['callbacks']['request'][0] ?? '') == HttpServer::class) {
            try {
                $tcpRouter = $container->get(JsonRpcHttpRouter::class);
                $router = $tcpRouter->getRouter($serverName);
            } catch (Throwable $throwable) {
                $logger->warning($throwable);
                return;
            }
        } else {
            $router = $container->get(DispatcherFactory::class)->getRouter($serverName);
        }

        $routerData = $router->getData();
        array_walk_recursive($routerData, function ($item) use ($scanAnnotation) {
            if ($item instanceof Handler && ! ($item->callback instanceof Closure)) {
                $prepareHandler = $this->prepareHandler($item->callback);
                if (count($prepareHandler) > 1) {
                    [$controller, $action] = $prepareHandler;
                    $scanAnnotation->scan($controller, $action);
                }
            }
        });
        $eventDispatcher->dispatch(new AfterDtoStart($serverConfig, $router));
        $scanAnnotation->clearScanClassArray();
    }

    protected function prepareHandler($handler): array
    {
        if (is_string($handler)) {
            if (str_contains($handler, '@')) {
                return explode('@', $handler);
            }
            return explode('::', $handler);
        }
        if (is_array($handler) && isset($handler[0], $handler[1])) {
            return $handler;
        }
        throw new RuntimeException('Handler not exist.');
    }
}
