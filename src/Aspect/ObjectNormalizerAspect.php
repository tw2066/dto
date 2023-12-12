<?php

declare(strict_types=1);

namespace Hyperf\DTO\Aspect;

use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\DTO\Mapper;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

use function Hyperf\Support\make;

class ObjectNormalizerAspect
{
    public array $classes = [
        AbstractObjectNormalizer::class . '::denormalize',
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
