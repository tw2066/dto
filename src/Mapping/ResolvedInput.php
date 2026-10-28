<?php

declare(strict_types=1);

namespace Hyperf\DTO\Mapping;

/**
 * @internal Valid 验证、后续映射和 RPC denormalize 共用的一次解析结果
 */
class ResolvedInput
{
    public function __construct(
        public string $className,
        public array $data,
        public MappingContext $context,
        public array $mappedTypes = [],
    ) {}
}
