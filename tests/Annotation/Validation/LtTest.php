<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Lt;

/**
 * @internal
 * @coversNothing
 */
class LtTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Lt(10, 'lt message');

        $this->assertPasses(
            ['number' => 5],
            ['number' => [$annotation->getRule()]],
            ['number.lt' => 'lt message']
        );

        $this->assertPasses(
            ['number' => 8],
            ['number' => [$annotation->getRule()]],
            ['number.lt' => 'lt message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Lt(10, 'lt message');

        $this->assertFailsWithMessage(
            ['number' => 10],
            ['number' => [$annotation->getRule()]],
            ['number.lt' => 'lt message'],
            'number',
            'lt message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Lt(10, 'lt message');

        $this->assertPasses(
            ['number' => ''],
            ['number' => [$annotation->getRule()]],
            ['number.lt' => 'lt message']
        );

        $this->assertFailsWithMessage(
            ['number' => null],
            ['number' => [$annotation->getRule()]],
            ['number.lt' => 'lt message'],
            'number',
            'lt message'
        );

        $this->assertFailsWithMessage(
            ['number' => 15],
            ['number' => [$annotation->getRule()]],
            ['number.lt' => 'lt message'],
            'number',
            'lt message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Lt(10, 'lt message');

        $this->assertPasses(
            [],
            ['number' => ['sometimes', $annotation->getRule()]],
            ['number.lt' => 'lt message']
        );

        $this->assertFailsWithMessage(
            ['number' => 10],
            ['number' => ['sometimes', $annotation->getRule()]],
            ['number.lt' => 'lt message'],
            'number',
            'lt message'
        );
    }
}
