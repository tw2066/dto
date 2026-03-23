<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use SplFileInfo;

class FakeFile extends SplFileInfo
{
    public function __construct(string $filename, private string $mimeType)
    {
        parent::__construct($filename);
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }
}
