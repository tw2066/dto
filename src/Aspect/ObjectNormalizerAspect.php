<?php

declare(strict_types=1);

namespace Hyperf\DTO\Aspect;

use Hyperf\Di\Aop\ProceedingJoinPoint;

class ObjectNormalizerAspect
{
    public array $classes = [
        'Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer::denormalize',
    ];

    /**
     * @return mixed
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        return $proceedingJoinPoint->process();
    }
}
