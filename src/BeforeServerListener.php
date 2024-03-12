<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Closure;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\DTO\Event\AfterDtoStart;
use Hyperf\DTO\Event\BeforeDtoStart;
use Hyperf\DTO\Router\TcpRouter;
use Hyperf\DTO\Scan\Scan;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeServerStart;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\HttpServer\Router\Handler;
use Hyperf\Server\Event\MainCoroutineServerStart;
use Hyperf\Stringable\Str;
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
        $eventDispatcher = $container->get(EventDispatcherInterface::class);
        $scanAnnotation = $container->get(Scan::class);
        $serverConfig = collect($config->get('server.servers'))->where('name', $serverName)->first();
        if (isset($serverConfig['callbacks']['receive'][0]) && Str::contains($serverConfig['callbacks']['receive'][0], 'TcpServer')) {
            try {
                $tcpRouter = $container->get(TcpRouter::class);
                $router = $tcpRouter->getRouter($serverName);
            } catch (Throwable) {
                return;
            }
        } else {
            $router = $container->get(DispatcherFactory::class)->getRouter($serverName);
        }
        $data = $router->getData();
        array_walk_recursive($data, function ($item) use ($scanAnnotation) {
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
