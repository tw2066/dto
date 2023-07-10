<?php

declare(strict_types=1);

namespace Hyperf\DTO\Serializer;

use Hyperf\DTO\Mapper;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use function Hyperf\Support\make;

class ObjectDtoNormalizer extends ObjectNormalizer
{
    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        return Mapper::map($data, make($type));
    }
}
