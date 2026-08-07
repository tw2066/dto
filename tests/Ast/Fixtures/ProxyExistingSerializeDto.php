<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Ast\Fixtures;

class ProxyExistingSerializeDto
{
    public string $title = '';

    public function jsonSerialize(): array
    {
        return ['title' => $this->title];
    }
}
