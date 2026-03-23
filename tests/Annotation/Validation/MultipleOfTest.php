<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\MultipleOf;

/**
 * @internal
 * @coversNothing
 */
class MultipleOfTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new MultipleOf(5, 'multiple_of message');

        $this->assertPasses(
            ['number' => 10],
            ['number' => [$annotation->getRule()]],
            ['number.multiple_of' => 'multiple_of message']
        );

        $this->assertPasses(
            ['number' => 15],
            ['number' => [$annotation->getRule()]],
            ['number.multiple_of' => 'multiple_of message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new MultipleOf(5, 'multiple_of message');

        $this->assertFailsWithMessage(
            ['number' => 7],
            ['number' => [$annotation->getRule()]],
            ['number.multiple_of' => 'multiple_of message'],
            'number',
            'multiple_of message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new MultipleOf(5, 'multiple_of message');

        $this->assertPasses(
            ['number' => ''],
            ['number' => [$annotation->getRule()]],
            ['number.multiple_of' => 'multiple_of message']
        );

        $this->assertFailsWithMessage(
            ['number' => null],
            ['number' => [$annotation->getRule()]],
            ['number.multiple_of' => 'multiple_of message'],
            'number',
            'multiple_of message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new MultipleOf(5, 'multiple_of message');

        $this->assertPasses(
            [],
            ['number' => ['numeric', $annotation->getRule()]],
            ['number.multiple_of' => 'multiple_of message']
        );

        $this->assertFailsWithMessage(
            ['number' => 7],
            ['number' => ['integer', $annotation->getRule()]],
            ['number.multiple_of' => 'multiple_of message'],
            'number',
            'multiple_of message'
        );
    }
}
