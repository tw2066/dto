<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Url;

/**
 * @internal
 * @coversNothing
 */
class UrlTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Url('url message');

        $this->assertPasses(
            ['website' => 'https://example.com'],
            ['website' => [$annotation->getRule()]],
            ['website.url' => 'url message']
        );

        $this->assertPasses(
            ['website' => 'http://example.org'],
            ['website' => [$annotation->getRule()]],
            ['website.url' => 'url message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Url('url message');

        $this->assertFailsWithMessage(
            ['website' => 'not-a-url'],
            ['website' => [$annotation->getRule()]],
            ['website.url' => 'url message'],
            'website',
            'url message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Url('url message');

        $this->assertPasses(
            ['website' => 'https://example.com/path?query=1'],
            ['website' => [$annotation->getRule()]],
            ['website.url' => 'url message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Url('url message');

        $this->assertPasses(
            [],
            ['website' => ['sometimes', $annotation->getRule()]],
            ['website.url' => 'url message']
        );
    }
}
