<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\ActiveUrl;

/**
 * @internal
 * @coversNothing
 */
class ActiveUrlTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new ActiveUrl('active_url message');

        $this->assertPasses(
            ['url' => 'https://example.com'],
            ['url' => [$annotation->getRule()]],
            ['url.active_url' => 'active_url message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new ActiveUrl('active_url message');

        $this->assertFailsWithMessage(
            ['url' => 'https://nonexistent.invalid'],
            ['url' => [$annotation->getRule()]],
            ['url.active_url' => 'active_url message'],
            'url',
            'active_url message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new ActiveUrl('active_url message');

        $this->assertPasses(
            ['url' => ''],
            ['url' => [$annotation->getRule()]],
            ['url.active_url' => 'active_url message']
        );

        $this->assertFailsWithMessage(
            ['url' => 0],
            ['url' => [$annotation->getRule()]],
            ['url.active_url' => 'active_url message'],
            'url',
            'active_url message'
        );

        $this->assertFailsWithMessage(
            ['url' => null],
            ['url' => [$annotation->getRule()]],
            ['url.active_url' => 'active_url message'],
            'url',
            'active_url message'
        );

        $this->assertFailsWithMessage(
            ['url' => []],
            ['url' => [$annotation->getRule()]],
            ['url.active_url' => 'active_url message'],
            'url',
            'active_url message'
        );

        $this->assertFailsWithMessage(
            ['url' => (object) ['a' => 1]],
            ['url' => [$annotation->getRule()]],
            ['url.active_url' => 'active_url message'],
            'url',
            'active_url message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new ActiveUrl('active_url message');

        $this->assertPasses(
            [],
            ['url' => ['sometimes', $annotation->getRule()]],
            ['url.active_url' => 'active_url message']
        );

        $this->assertFailsWithMessage(
            ['url' => 'https://nonexistent.invalid'],
            ['url' => ['sometimes', $annotation->getRule()]],
            ['url.active_url' => 'active_url message'],
            'url',
            'active_url message'
        );
    }
}
