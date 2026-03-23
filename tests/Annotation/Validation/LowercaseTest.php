<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Lowercase;

/**
 * @internal
 * @coversNothing
 */
class LowercaseTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Lowercase('lowercase message');

        $this->assertPasses(
            ['text' => 'hello'],
            ['text' => [$annotation->getRule()]],
            ['text.lowercase' => 'lowercase message']
        );

        $this->assertPasses(
            ['text' => 'hello world'],
            ['text' => [$annotation->getRule()]],
            ['text.lowercase' => 'lowercase message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Lowercase('lowercase message');

        $this->assertFailsWithMessage(
            ['text' => 'Hello'],
            ['text' => [$annotation->getRule()]],
            ['text.lowercase' => 'lowercase message'],
            'text',
            'lowercase message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Lowercase('lowercase message');

        $this->assertPasses(
            ['text' => ''],
            ['text' => [$annotation->getRule()]],
            ['text.lowercase' => 'lowercase message']
        );

        $this->assertFailsWithMessage(
            ['text' => 'A'],
            ['text' => [$annotation->getRule()]],
            ['text.lowercase' => 'lowercase message'],
            'text',
            'lowercase message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Lowercase('lowercase message');

        $this->assertPasses(
            [],
            ['text' => ['sometimes', $annotation->getRule()]],
            ['text.lowercase' => 'lowercase message']
        );

        $this->assertFailsWithMessage(
            ['text' => 'Hello'],
            ['text' => ['sometimes', $annotation->getRule()]],
            ['text.lowercase' => 'lowercase message'],
            'text',
            'lowercase message'
        );
    }
}
