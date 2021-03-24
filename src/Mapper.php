<?php

namespace Tang\DTO;

use Hyperf\Utils\ApplicationContext;
use JsonMapper;
use Psr\Container\ContainerInterface;

class Mapper
{
    private ContainerInterface $container;

    public function __construct()
    {
        $this->container = ApplicationContext::getContainer();

    }
    public function map($json, $object)
    {
        $mapper = $this->container->get(JsonMapper::class);
        $mapper->bEnforceMapType = false;
        return $mapper->map($json, $object);
    }

}