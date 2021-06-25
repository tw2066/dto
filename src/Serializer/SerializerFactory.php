<?php

declare(strict_types=1);

namespace Hyperf\DTO\Serializer;

use Hyperf\Utils\Serializer\ExceptionNormalizer;
use Hyperf\Utils\Serializer\ScalarNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;

class SerializerFactory
{
    protected string $serializer;

    public function __construct(string $serializer = Serializer::class)
    {
        $this->serializer = $serializer;
    }

    public function __invoke()
    {
        return new $this->serializer([
            new ExceptionNormalizer(),
            new ObjectDtoNormalizer(),
            new ArrayDenormalizer(),
            new ScalarNormalizer(),
        ]);
    }
}
