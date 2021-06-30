<?php

declare(strict_types=1);

namespace Hyperf\DTO\Aspect;

use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\DTO\Annotation\Proxy\Data;

# [Aspect]
class DtoData extends AbstractAspect
{
    public $annotations = [
        Data::class,
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        return $proceedingJoinPoint->process();
    }
}
