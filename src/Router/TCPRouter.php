<?php

declare(strict_types=1);

namespace Hyperf\DTO\Router;

use Hyperf\JsonRpc\TcpServer;
use Hyperf\Rpc\Protocol;
use Hyperf\RpcServer\Router\DispatcherFactory;
use Psr\Container\ContainerInterface;

class TCPRouter
{
    /**
     * @var mixed|TcpServer
     */
    private $tcpServer;

    public function __construct(ContainerInterface $container)
    {
        $this->tcpServer = $container->get(TcpServer::class);
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
        return $getResponseBuilder->call($this->tcpServer);
    }
}
