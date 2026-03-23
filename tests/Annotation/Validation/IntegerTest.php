<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Integer;

/**
 * @internal
 * @coversNothing
 */
class IntegerTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Integer('integer message');

        $this->assertPasses(
            ['number' => 42],
            ['number' => [$annotation->getRule()]],
            ['number.integer' => 'integer message']
        );

        $this->assertPasses(
            ['number' => '42'],
            ['number' => [$annotation->getRule()]],
            ['number.integer' => 'integer message']
        );

        $this->assertPasses(
            ['number' => -123],
            ['number' => [$annotation->getRule()]],
            ['number.integer' => 'integer message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Integer('integer message');

        $this->assertFailsWithMessage(
            ['number' => 'not-a-number'],
            ['number' => [$annotation->getRule()]],
            ['number.integer' => 'integer message'],
            'number',
            'integer message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Integer('integer message');

        $this->assertPasses(
            ['number' => ''],
            ['number' => [$annotation->getRule()]],
            ['number.integer' => 'integer message']
        );

        $this->assertFailsWithMessage(
            ['number' => null],
            ['number' => [$annotation->getRule()]],
            ['number.integer' => 'integer message'],
            'number',
            'integer message'
        );

        $this->assertFailsWithMessage(
            ['number' => 12.34],
            ['number' => [$annotation->getRule()]],
            ['number.integer' => 'integer message'],
            'number',
            'integer message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Integer('integer message');

        $this->assertPasses(
            [],
            ['number' => ['sometimes', $annotation->getRule()]],
            ['number.integer' => 'integer message']
        );

        $this->assertFailsWithMessage(
            ['number' => 'not-a-number'],
            ['number' => ['sometimes', $annotation->getRule()]],
            ['number.integer' => 'integer message'],
            'number',
            'integer message'
        );
    }
}
