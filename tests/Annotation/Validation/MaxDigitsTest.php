<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\MaxDigits;

/**
 * @internal
 * @coversNothing
 */
class MaxDigitsTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new MaxDigits(5, 'max_digits message');

        $this->assertPasses(
            ['number' => 123],
            ['number' => [$annotation->getRule()]],
            ['number.max_digits' => 'max_digits message']
        );

        $this->assertPasses(
            ['number' => 12345],
            ['number' => [$annotation->getRule()]],
            ['number.max_digits' => 'max_digits message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new MaxDigits(5, 'max_digits message');

        $this->assertFailsWithMessage(
            ['number' => 123456],
            ['number' => [$annotation->getRule()]],
            ['number.max_digits' => 'max_digits message'],
            'number',
            'max_digits message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new MaxDigits(1, 'max_digits message');

        $this->assertPasses(
            ['number' => ''],
            ['number' => [$annotation->getRule()]],
            ['number.max_digits' => 'max_digits message']
        );

        $this->assertFailsWithMessage(
            ['number' => 11],
            ['number' => ['integer', $annotation->getRule()]],
            ['number.max_digits' => 'max_digits message'],
            'number',
            'max_digits message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new MaxDigits(5, 'max_digits message');

        $this->assertPasses(
            [],
            ['number' => ['sometimes', $annotation->getRule()]],
            ['number.max_digits' => 'max_digits message']
        );

        $this->assertFailsWithMessage(
            ['number' => 123456],
            ['number' => ['integer', 'sometimes', $annotation->getRule()]],
            ['number.max_digits' => 'max_digits message'],
            'number',
            'max_digits message'
        );
    }
}
