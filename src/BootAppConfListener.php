<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Contract\ConfigInterface;
use Hyperf\DTO\Event\AfterDTOStart;
use Hyperf\DTO\Scan\ScanAnnotation;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\HttpServer\Router\Handler;
use Hyperf\Utils\ApplicationContext;
use Psr\EventDispatcher\EventDispatcherInterface;

class BootAppConfListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    public function process(object $event)
    {
        $container = ApplicationContext::getContainer();
        $config = $container->get(ConfigInterface::class);
        $event = $container->get(EventDispatcherInterface::class);
        $scanAnnotation = $container->get(ScanAnnotation::class);
        $servers = $config->get('server.servers');
        foreach ($servers as $server) {
            $router = $container->get(DispatcherFactory::class)->getRouter($server['name']);
            $data = $router->getData();
            array_walk_recursive($data, function ($item) use ($scanAnnotation) {
                if ($item instanceof Handler && ! ($item->callback instanceof \Closure)) {
                    [$controller, $action] = $this->prepareHandler($item->callback);
                    $scanAnnotation->scan($controller,$action);
                }
            });
        }
        $event->dispatch(new AfterDTOStart());
    }

    protected function prepareHandler($handler): array
    {
        if (is_string($handler)) {
            if (strpos($handler, '@') !== false) {
                return explode('@', $handler);
            }
            return explode('::', $handler);
        }
        if (is_array($handler) && isset($handler[0], $handler[1])) {
            return $handler;
        }
        throw new \RuntimeException('Handler not exist.');
    }
}
