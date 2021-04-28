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
use Hyperf\HttpServer\CoreMiddleware;
use Hyperf\DTO\Dependencies\SimpleNormalizer;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [

            'dependencies' => [
                NormalizerInterface::class => SimpleNormalizer::class,
                CoreMiddleware::class => Dependencies\CoreMiddleware::class
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
