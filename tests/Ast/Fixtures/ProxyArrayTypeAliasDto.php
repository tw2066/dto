<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Ast\Fixtures;

use Hyperf\DTO\Annotation\ArrayType;

class ProxyArrayTypeAliasDto
{
    #[ArrayType(ProxyArrayTypeItem::class)]
    public array $items = [];
}
