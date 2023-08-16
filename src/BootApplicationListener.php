<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\DTO\Ast\DtoVisitorProxyClass;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Psr\Container\ContainerInterface;

#[Listener(priority: 999)]
class BootApplicationListener implements ListenerInterface
{
    public function __construct(
        private ContainerInterface $container,
        private DtoVisitorProxyClass $dtoVisitorProxyClass
    ) {
    }

    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    public function process(object $event): void
    {
        $this->dtoVisitorProxyClass->generic();
    }
}
