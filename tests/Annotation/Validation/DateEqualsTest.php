<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\DateEquals;

/**
 * @internal
 * @coversNothing
 */
class DateEqualsTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new DateEquals('2020-01-01', 'date_equals message');

        $this->assertPasses(
            ['v' => '2020-01-01'],
            ['v' => [$annotation->getRule()]],
            ['v.date_equals' => 'date_equals message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new DateEquals('2020-01-01', 'date_equals message');

        $this->assertFailsWithMessage(
            ['v' => '2020-01-02'],
            ['v' => [$annotation->getRule()]],
            ['v.date_equals' => 'date_equals message'],
            'v',
            'date_equals message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new DateEquals('2020-01-01', 'date_equals message');

        $this->assertPasses(
            ['v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.date_equals' => 'date_equals message']
        );

        $this->assertFailsWithMessage(
            ['v' => null],
            ['v' => [$annotation->getRule()]],
            ['v.date_equals' => 'date_equals message'],
            'v',
            'date_equals message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new DateEquals('2020-01-01', 'date_equals message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.date_equals' => 'date_equals message']
        );

        $this->assertFailsWithMessage(
            ['v' => '2020-01-02'],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.date_equals' => 'date_equals message'],
            'v',
            'date_equals message'
        );
    }
}
