<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Min;

/**
 * @internal
 * @coversNothing
 */
class MinTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Min(10, 'min message');

        $this->assertPasses(
            ['number' => 15],
            ['number' => ['integer', $annotation->getRule()]],
            ['number.min' => 'min message']
        );

        $this->assertPasses(
            ['number' => 10],
            ['number' => ['integer', $annotation->getRule()]],
            ['number.min' => 'min message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Min(10, 'min message');

        $this->assertFailsWithMessage(
            ['number' => 5],
            ['number' => [$annotation->getRule()]],
            ['number.min' => 'min message'],
            'number',
            'min message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Min(10, 'min message');

        $this->assertPasses(
            ['number' => ''],
            ['number' => [$annotation->getRule()]],
            ['number.min' => 'min message']
        );

        $this->assertFailsWithMessage(
            ['number' => null],
            ['number' => [$annotation->getRule()]],
            ['number.min' => 'min message'],
            'number',
            'min message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Min(10, 'min message');

        $this->assertPasses(
            [],
            ['number' => ['sometimes', $annotation->getRule()]],
            ['number.min' => 'min message']
        );

        $this->assertFailsWithMessage(
            ['number' => 5],
            ['number' => ['sometimes', $annotation->getRule()]],
            ['number.min' => 'min message'],
            'number',
            'min message'
        );
    }
}
