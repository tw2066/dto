<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use App\Kernel\Http\Response;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DTO\Event\AfterDTOStart;
use Hyperf\DTO\Router\TCPRouter;
use Hyperf\DTO\Scan\ScanAnnotation;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\Framework\Event\BeforeServerStart;
use Hyperf\Framework\Event\BeforeWorkerStart;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\HttpServer\Router\Handler;
use Hyperf\JsonRpc\TcpServer;
use Hyperf\Rpc\Protocol;
use Hyperf\RpcServer\CoreMiddleware;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Str;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;


class BootAppConfListener implements ListenerInterface
{

    public function listen(): array
    {
        return [
            BeforeServerStart::class
        ];
    }

    /**
     * @param BeforeServerStart $event
     */
    public function process(object $event)
    {

        $serverName = $event->serverName;
        $container = ApplicationContext::getContainer();
        $config = $container->get(ConfigInterface::class);
        $event = $container->get(EventDispatcherInterface::class);
        $scanAnnotation = $container->get(ScanAnnotation::class);
        $container->get(Mapper::class);

        $serverConfig = collect($config->get('server.servers'))->where('name', $serverName)->first();
        if (isset($serverConfig['callbacks']['receive'][0]) && Str::contains($serverConfig['callbacks']['receive'][0], 'TcpServer')) {
            $tcpRouter = $container->get(TCPRouter::class);
            $router = $tcpRouter->getRouter($serverName);
            $data = $router->getData();
        } else {
            $router = $container->get(DispatcherFactory::class)->getRouter($serverName);
            $data = $router->getData();
        }

        array_walk_recursive($data, function ($item) use ($scanAnnotation) {
            if ($item instanceof Handler && !($item->callback instanceof \Closure)) {
                [$controller, $action] = $this->prepareHandler($item->callback);
                $scanAnnotation->scan($controller, $action);
            }
        });
        $event->dispatch(new AfterDTOStart($serverConfig, $router));
        $scanAnnotation->clearScanClassArray();
    }

    protected function getServerConfig()
    {

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
