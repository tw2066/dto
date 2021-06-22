<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Contract\NormalizerInterface;
use Hyperf\DTO\Serializer\SerializerFactory;
use Hyperf\HttpServer\CoreMiddleware;
use Hyperf\Utils\Serializer\Serializer;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                CoreMiddleware::class => Middleware\CoreMiddleware::class,
                NormalizerInterface::class => new SerializerFactory(Serializer::class),
            ],
            'listeners' => [
                BeforeServerListener::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
            ],
        ];
    }
}
