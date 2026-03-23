<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Date;

/**
 * @internal
 * @coversNothing
 */
class DateTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Date('date message');

        $this->assertPasses(
            ['date' => '2024-01-01'],
            ['date' => [$annotation->getRule()]],
            ['date.date' => 'date message']
        );

        $this->assertPasses(
            ['date' => 'January 1, 2024'],
            ['date' => [$annotation->getRule()]],
            ['date.date' => 'date message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Date('date message');

        $this->assertFailsWithMessage(
            ['date' => 'invalid date'],
            ['date' => [$annotation->getRule()]],
            ['date.date' => 'date message'],
            'date',
            'date message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Date('date message');

        $this->assertPasses(
            ['date' => ''],
            ['date' => [$annotation->getRule()]],
            ['date.date' => 'date message']
        );

        $this->assertFailsWithMessage(
            ['date' => null],
            ['date' => [$annotation->getRule()]],
            ['date.date' => 'date message'],
            'date',
            'date message'
        );

        $this->assertFailsWithMessage(
            ['date' => 0],
            ['date' => [$annotation->getRule()]],
            ['date.date' => 'date message'],
            'date',
            'date message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Date('date message');

        $this->assertPasses(
            [],
            ['date' => ['sometimes', $annotation->getRule()]],
            ['date.date' => 'date message']
        );

        $this->assertFailsWithMessage(
            ['date' => 'invalid date'],
            ['date' => ['sometimes', $annotation->getRule()]],
            ['date.date' => 'date message'],
            'date',
            'date message'
        );
    }
}
