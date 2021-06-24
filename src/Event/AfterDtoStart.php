<?php

declare(strict_types=1);

namespace Hyperf\DTO\Event;

use Hyperf\HttpServer\Router\RouteCollector;

class AfterDtoStart
{
    /**
     * @var string
     */
    public $serverConfig;

    /**
     * @var RouteCollector
     */
    public $router;

    public function __construct(array $serverConfig, $router)
    {
        $this->router = $router;
        $this->serverConfig = $serverConfig;
    }
}
