<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\HttpServer\CoreMiddleware;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                CoreMiddleware::class => Middleware\CoreMiddleware::class,
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
