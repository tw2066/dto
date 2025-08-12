<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\DTO\Annotation\JSONField;
use Hyperf\DTO\Ast\DtoProxyClass;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Psr\Container\ContainerInterface;

#[Listener(priority: 999)]
class BootApplicationListener implements ListenerInterface
{
    public function __construct(
        private ContainerInterface $container,
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
        //$this->replaceAliasPropertyAnnotation();
    }

    //public function replaceAliasPropertyAnnotation(): void
    //{
    //    $classes = AnnotationCollector::getPropertiesByAnnotation(JSONField::class);
    //    foreach ($classes as $class) {
    //        $alias = $class['annotation']->name;
    //        AnnotationCollector::replaceProperty($class['class'], $alias, $class['property']);
    //    }
    //}
}
