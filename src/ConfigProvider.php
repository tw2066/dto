<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\DTO\Aspect\CoreMiddlewareAspect;
use Hyperf\HttpServer\CoreMiddleware;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'aspects' => [
                CoreMiddlewareAspect::class,
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
