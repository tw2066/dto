<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\In;

/**
 * @internal
 * @coversNothing
 */
class InTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new In(['a', 'b', 'c'], 'in message');

        $this->assertPasses(
            ['choice' => 'a'],
            ['choice' => [$annotation->getRule()]],
            ['choice.in' => 'in message']
        );

        $this->assertPasses(
            ['choice' => 'b'],
            ['choice' => [$annotation->getRule()]],
            ['choice.in' => 'in message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new In(['a', 'b', 'c'], 'in message');

        $this->assertFailsWithMessage(
            ['choice' => 'd'],
            ['choice' => [$annotation->getRule()]],
            ['choice.in' => 'in message'],
            'choice',
            'in message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new In(['a', 'b', 'c'], 'in message');

        $this->assertPasses(
            ['choice' => ''],
            ['choice' => [$annotation->getRule()]],
            ['choice.in' => 'in message']
        );

        $this->assertFailsWithMessage(
            ['choice' => null],
            ['choice' => [$annotation->getRule()]],
            ['choice.in' => 'in message'],
            'choice',
            'in message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new In(['a', 'b', 'c'], 'in message');

        $this->assertPasses(
            [],
            ['choice' => ['sometimes', $annotation->getRule()]],
            ['choice.in' => 'in message']
        );

        $this->assertFailsWithMessage(
            ['choice' => 'd'],
            ['choice' => ['sometimes', $annotation->getRule()]],
            ['choice.in' => 'in message'],
            'choice',
            'in message'
        );
    }
}
