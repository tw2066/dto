<?php

declare(strict_types=1);

namespace Hyperf\DTO\Router;

use Hyperf\JsonRpc\HttpServer;
use Hyperf\Rpc\Protocol;
use Hyperf\RpcServer\Router\DispatcherFactory;
use Psr\Container\ContainerInterface;

use function Hyperf\Support\make;

class JsonRpcHttpRouter
{
    private HttpServer $server;

    private $protocol;

    public function __construct(ContainerInterface $container)
    {
        $this->server = $container->get(HttpServer::class);
    }

    public function getRouter($serverName)
    {
        $data = make(DispatcherFactory::class, [
            'pathGenerator' => $this->getProtocol()->getPathGenerator(),
        ]);
        return $data->getRouter($serverName);
    }

    protected function getProtocol(): Protocol
    {
        $getResponseBuilder = function () {
            return $this->protocol;
        };
        return $getResponseBuilder->call($this->server);
    }
}
