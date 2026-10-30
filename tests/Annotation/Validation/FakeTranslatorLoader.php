<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\Contract\TranslatorLoaderInterface;

class FakeTranslatorLoader implements TranslatorLoaderInterface
{
    public function __construct(private array $groups = [])
    {
    }

    public function load(string $locale, string $group, ?string $namespace = null): array
    {
        return $this->groups[$group] ?? [];
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
