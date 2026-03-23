<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\MinDigits;

/**
 * @internal
 * @coversNothing
 */
class MinDigitsTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new MinDigits(5, 'min_digits message');

        $this->assertPasses(
            ['number' => 12345],
            ['number' => [$annotation->getRule()]],
            ['number.min_digits' => 'min_digits message']
        );

        $this->assertPasses(
            ['number' => 123456],
            ['number' => [$annotation->getRule()]],
            ['number.min_digits' => 'min_digits message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new MinDigits(5, 'min_digits message');

        $this->assertFailsWithMessage(
            ['number' => 123],
            ['number' => [$annotation->getRule()]],
            ['number.min_digits' => 'min_digits message'],
            'number',
            'min_digits message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new MinDigits(5, 'min_digits message');

        $this->assertPasses(
            ['number' => ''],
            ['number' => [$annotation->getRule()]],
            ['number.min_digits' => 'min_digits message']
        );

        $this->assertFailsWithMessage(
            ['number' => null],
            ['number' => [$annotation->getRule()]],
            ['number.min_digits' => 'min_digits message'],
            'number',
            'min_digits message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new MinDigits(5, 'min_digits message');

        $this->assertPasses(
            [],
            ['number' => ['sometimes', $annotation->getRule()]],
            ['number.min_digits' => 'min_digits message']
        );

        $this->assertFailsWithMessage(
            ['number' => 123],
            ['number' => ['sometimes', $annotation->getRule()]],
            ['number.min_digits' => 'min_digits message'],
            'number',
            'min_digits message'
        );
    }
}
