<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\DTO\Ast\DtoProxyClass;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;

#[Listener(priority: 999)]
class BootApplicationListener implements ListenerInterface
{
    public function __construct(
        private DtoProxyClass $dtoProxyClass
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
        $this->dtoProxyClass->generic();
    }
}
