<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Hyperf\DTO\Serializer;

use Hyperf\Utils\Serializer\ExceptionNormalizer;
use Hyperf\Utils\Serializer\ScalarNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;

class SerializerFactory
{
    /**
     * @var string
     */
    protected $serializer;

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
