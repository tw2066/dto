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
                BeforeServerListener::class
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
