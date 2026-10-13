<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Controller;

use Hyperf\DTO\Annotation\Contracts\RequestBody;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PostMapping;
use HyperfTest\DTO\Request\LazyScanRequest;

#[Controller(prefix: '/lazy_scan')]
class LazyScanController
{
    #[PostMapping(path: 'create')]
    public function create(#[RequestBody] LazyScanRequest $request): array
    {
        return [];
    }

    #[PostMapping(path: 'update')]
    public function update(#[RequestBody] LazyScanRequest $request): array
    {
        return [];
    }

    #[PostMapping(path: 'no_annotation')]
    public function noAnnotation(int $id): array
    {
        return [$id];
    }

    #[PostMapping(path: 'plain')]
    public function plain(int $id): array
    {
        return [$id];
    }
}
