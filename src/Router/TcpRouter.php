<?php

declare(strict_types=1);

namespace Hyperf\DTO\Router;

use Hyperf\JsonRpc\TcpServer;
use Hyperf\Rpc\Protocol;
use Hyperf\RpcServer\Router\DispatcherFactory;
use Psr\Container\ContainerInterface;

class TcpRouter
{
    private TcpServer $tcpServer;

    private $protocol;

    public function __construct(ContainerInterface $container)
    {
        $this->tcpServer = $container->get(TcpServer::class);
    }

    public function getRouter($serverName)
    {
        $data = \Hyperf\Support\make(DispatcherFactory::class, [
            'pathGenerator' => $this->getProtocol()->getPathGenerator(),
        ]);
        return $data->getRouter($serverName);
    }

    protected function getProtocol(): Protocol
    {
        $getResponseBuilder = function () {
            return $this->protocol;
        };
        return $getResponseBuilder->call($this->tcpServer);
    }
}
