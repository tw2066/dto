<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Numeric;

/**
 * @internal
 * @coversNothing
 */
class NumericTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Numeric('numeric message');

        $this->assertPasses(
            ['number' => 42],
            ['number' => [$annotation->getRule()]],
            ['number.numeric' => 'numeric message']
        );

        $this->assertPasses(
            ['number' => '42'],
            ['number' => [$annotation->getRule()]],
            ['number.numeric' => 'numeric message']
        );

        $this->assertPasses(
            ['number' => 12.34],
            ['number' => [$annotation->getRule()]],
            ['number.numeric' => 'numeric message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Numeric('numeric message');

        $this->assertFailsWithMessage(
            ['number' => 'not-a-number'],
            ['number' => [$annotation->getRule()]],
            ['number.numeric' => 'numeric message'],
            'number',
            'numeric message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Numeric('numeric message');

        $this->assertPasses(
            ['number' => ''],
            ['number' => [$annotation->getRule()]],
            ['number.numeric' => 'numeric message']
        );

        $this->assertFailsWithMessage(
            ['number' => null],
            ['number' => [$annotation->getRule()]],
            ['number.numeric' => 'numeric message'],
            'number',
            'numeric message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Numeric('numeric message');

        $this->assertPasses(
            [],
            ['number' => ['sometimes', $annotation->getRule()]],
            ['number.numeric' => 'numeric message']
        );

        $this->assertFailsWithMessage(
            ['number' => 'not-a-number'],
            ['number' => ['sometimes', $annotation->getRule()]],
            ['number.numeric' => 'numeric message'],
            'number',
            'numeric message'
        );
    }
}
