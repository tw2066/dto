<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\DateFormat;

/**
 * @internal
 * @coversNothing
 */
class DateFormatTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new DateFormat('Y-m-d', 'date_format message');

        $this->assertPasses(
            ['date' => '2024-01-01'],
            ['date' => [$annotation->getRule()]],
            ['date.date_format' => 'date_format message']
        );

        $this->assertPasses(
            ['date' => '2024-12-31'],
            ['date' => [$annotation->getRule()]],
            ['date.date_format' => 'date_format message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new DateFormat('Y-m-d', 'date_format message');

        $this->assertFailsWithMessage(
            ['date' => '2024/01/01'],
            ['date' => [$annotation->getRule()]],
            ['date.date_format' => 'date_format message'],
            'date',
            'date_format message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new DateFormat('Y-m-d', 'date_format message');

        $this->assertPasses(
            ['date' => ''],
            ['date' => [$annotation->getRule()]],
            ['date.date_format' => 'date_format message']
        );

        $this->assertFailsWithMessage(
            ['date' => null],
            ['date' => [$annotation->getRule()]],
            ['date.date_format' => 'date_format message'],
            'date',
            'date_format message'
        );

        $this->assertFailsWithMessage(
            ['date' => '01-01-2024'],
            ['date' => [$annotation->getRule()]],
            ['date.date_format' => 'date_format message'],
            'date',
            'date_format message'
        );
    }

    public function testDifferentFormats(): void
    {
        $annotation = new DateFormat('Y/m/d H:i:s', 'date_format message');

        $this->assertPasses(
            ['date' => '2024/01/01 12:00:00'],
            ['date' => [$annotation->getRule()]],
            ['date.date_format' => 'date_format message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new DateFormat('Y-m-d', 'date_format message');

        $this->assertPasses(
            [],
            ['date' => ['sometimes', $annotation->getRule()]],
            ['date.date_format' => 'date_format message']
        );

        $this->assertFailsWithMessage(
            ['date' => '2024/01/01'],
            ['date' => ['sometimes', $annotation->getRule()]],
            ['date.date_format' => 'date_format message'],
            'date',
            'date_format message'
        );
    }
}
