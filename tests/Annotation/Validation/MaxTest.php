<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Max;

/**
 * @internal
 * @coversNothing
 */
class MaxTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Max(10, 'max message');

        $this->assertPasses(
            ['number' => 5],
            ['number' => [$annotation->getRule()]],
            ['number.max' => 'max message']
        );

        $this->assertPasses(
            ['number' => 10],
            ['number' => [$annotation->getRule()]],
            ['number.max' => 'max message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Max(10, 'max message');

        $this->assertFailsWithMessage(
            ['number' => 11],
            ['number' => ['numeric', $annotation->getRule()]],
            ['number.max' => 'max message'],
            'number',
            'max message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Max(10, 'max message');

        $this->assertPasses(
            ['number' => 1],
            ['number' => ['numeric', $annotation->getRule()]],
            ['number.max' => 'max message']
        );

        $this->assertFailsWithMessage(
            ['number' => 11],
            ['number' => ['numeric', $annotation->getRule()]],
            ['number.max' => 'max message'],
            'number',
            'max message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Max(10, 'max message');

        $this->assertPasses(
            [],
            ['number' => ['sometimes', $annotation->getRule()]],
            ['number.max' => 'max message']
        );

        $this->assertFailsWithMessage(
            ['number' => 11],
            ['number' => ['numeric', 'sometimes', $annotation->getRule()]],
            ['number.max' => 'max message'],
            'number',
            'max message'
        );
    }
}
