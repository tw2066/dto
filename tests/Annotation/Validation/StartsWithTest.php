<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\StartsWith;

/**
 * @internal
 * @coversNothing
 */
class StartsWithTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new StartsWith('http', 'starts with message');

        $this->assertPasses(
            ['url' => 'http://example.com'],
            ['url' => [$annotation->getRule()]],
            ['url.starts_with' => 'starts with message']
        );

        $annotationHttps = new StartsWith('https', 'starts with message');
        $this->assertPasses(
            ['url' => 'https://example.com'],
            ['url' => [$annotationHttps->getRule()]],
            ['url.starts_with' => 'starts with message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new StartsWith('http', 'starts with message');

        $this->assertFailsWithMessage(
            ['url' => 'ftp://example.com'],
            ['url' => [$annotation->getRule()]],
            ['url.starts_with' => 'starts with message'],
            'url',
            'starts with message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new StartsWith('a', 'starts with message');

        $this->assertPasses(
            ['name' => 'a'],
            ['name' => [$annotation->getRule()]],
            ['name.starts_with' => 'starts with message']
        );

        $annotationA = new StartsWith('A', 'starts with message');
        $this->assertPasses(
            ['name' => 'Apple'],
            ['name' => [$annotationA->getRule()]],
            ['name.starts_with' => 'starts with message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new StartsWith('http', 'starts with message');

        $this->assertPasses(
            [],
            ['url' => ['sometimes', $annotation->getRule()]],
            ['url.starts_with' => 'starts with message']
        );
    }
}
