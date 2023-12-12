<?php

declare(strict_types=1);

namespace Hyperf\DTO\Aspect;

use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\DTO\Mapper;

use function Hyperf\Support\make;

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
        $data = $proceedingJoinPoint->arguments['keys']['data'];
        $type = $proceedingJoinPoint->arguments['keys']['type'];
        return Mapper::map($data, make($type));
    }
}
