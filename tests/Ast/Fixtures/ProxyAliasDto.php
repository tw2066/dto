<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Ast\Fixtures;

class ProxyAliasDto
{
    public string $name = '';

    public function getName(): string
    {
        return $this->name;
    }
}
