<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\After;

/**
 * @internal
 * @coversNothing
 */
class AfterTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new After('2024-01-01', 'after message');

        $this->assertPasses(
            ['date' => '2024-01-02'],
            ['date' => [$annotation->getRule()]],
            ['date.after' => 'after message']
        );

        $this->assertPasses(
            ['date' => '2025-01-01'],
            ['date' => [$annotation->getRule()]],
            ['date.after' => 'after message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new After('2024-01-01', 'after message');

        $this->assertFailsWithMessage(
            ['date' => '2023-12-31'],
            ['date' => [$annotation->getRule()]],
            ['date.after' => 'after message'],
            'date',
            'after message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new After('2024-01-01', 'after message');

        $this->assertFailsWithMessage(
            ['date' => '2024-01-01'],
            ['date' => [$annotation->getRule()]],
            ['date.after' => 'after message'],
            'date',
            'after message'
        );

        $this->assertPasses(
            ['date' => ''],
            ['date' => [$annotation->getRule()]],
            ['date.after' => 'after message']
        );

        $this->assertFailsWithMessage(
            ['date' => 0],
            ['date' => [$annotation->getRule()]],
            ['date.after' => 'after message'],
            'date',
            'after message'
        );

        $this->assertFailsWithMessage(
            ['date' => null],
            ['date' => [$annotation->getRule()]],
            ['date.after' => 'after message'],
            'date',
            'after message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new After('2024-01-01', 'after message');

        $this->assertPasses(
            [],
            ['date' => ['sometimes', $annotation->getRule()]],
            ['date.after' => 'after message']
        );

        $this->assertFailsWithMessage(
            ['date' => '2023-12-31'],
            ['date' => ['sometimes', $annotation->getRule()]],
            ['date.after' => 'after message'],
            'date',
            'after message'
        );
    }
}
