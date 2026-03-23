<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Gt;

/**
 * @internal
 * @coversNothing
 */
class GtTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Gt(10, 'gt message');

        $this->assertPasses(
            ['number' => 15],
            ['number' => [$annotation->getRule()]],
            ['number.gt' => 'gt message']
        );

        $this->assertPasses(
            ['number' => 20],
            ['number' => [$annotation->getRule()]],
            ['number.gt' => 'gt message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Gt(10, 'gt message');

        $this->assertFailsWithMessage(
            ['number' => 10],
            ['number' => [$annotation->getRule()]],
            ['number.gt' => 'gt message'],
            'number',
            'gt message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Gt(10, 'gt message');

        $this->assertPasses(
            ['number' => ''],
            ['number' => [$annotation->getRule()]],
            ['number.gt' => 'gt message']
        );

        $this->assertFailsWithMessage(
            ['number' => null],
            ['number' => [$annotation->getRule()]],
            ['number.gt' => 'gt message'],
            'number',
            'gt message'
        );

        $this->assertFailsWithMessage(
            ['number' => 5],
            ['number' => [$annotation->getRule()]],
            ['number.gt' => 'gt message'],
            'number',
            'gt message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Gt(10, 'gt message');

        $this->assertPasses(
            [],
            ['number' => ['sometimes', $annotation->getRule()]],
            ['number.gt' => 'gt message']
        );

        $this->assertFailsWithMessage(
            ['number' => 10],
            ['number' => ['sometimes', $annotation->getRule()]],
            ['number.gt' => 'gt message'],
            'number',
            'gt message'
        );
    }
}
