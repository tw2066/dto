<?php

declare(strict_types=1);

namespace Hyperf\DTO\Event;

use Hyperf\HttpServer\Router\RouteCollector;

class AfterDtoStart
{
    public array $serverConfig;

    public RouteCollector $router;

    public function __construct(array $serverConfig, $router)
    {
        $this->router = $router;
        $this->serverConfig = $serverConfig;
    }
}
