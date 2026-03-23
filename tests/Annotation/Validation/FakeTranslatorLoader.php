<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\Contract\TranslatorLoaderInterface;

class FakeTranslatorLoader implements TranslatorLoaderInterface
{
    public function load(string $locale, string $group, ?string $namespace = null): array
    {
        return [];
    }

    public function addNamespace(string $namespace, string $hint)
    {
    }

    public function addJsonPath(string $path)
    {
    }

    public function namespaces(): array
    {
        return [];
    }
}
