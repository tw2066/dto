<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Decimal;

/**
 * @internal
 * @coversNothing
 */
class DecimalTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Decimal(1, 2, 'decimal message');

        $this->assertPasses(
            ['v' => '1.2'],
            ['v' => [$annotation->getRule()]],
            ['v.decimal' => 'decimal message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Decimal(1, 2, 'decimal message');

        $this->assertFailsWithMessage(
            ['v' => '1.234'],
            ['v' => [$annotation->getRule()]],
            ['v.decimal' => 'decimal message'],
            'v',
            'decimal message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Decimal(1, 2, 'decimal message');

        $this->assertPasses(
            ['v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.decimal' => 'decimal message']
        );

        $this->assertFailsWithMessage(
            ['v' => 0],
            ['v' => [$annotation->getRule()]],
            ['v.decimal' => 'decimal message'],
            'v',
            'decimal message'
        );

        $this->assertFailsWithMessage(
            ['v' => null],
            ['v' => [$annotation->getRule()]],
            ['v.decimal' => 'decimal message'],
            'v',
            'decimal message'
        );

        $this->assertFailsWithMessage(
            ['v' => new class () {
                public function __toString(): string
                {
                    return '1.2';
                }
            }],
            ['v' => [$annotation->getRule()]],
            ['v.decimal' => 'decimal message'],
            'v',
            'decimal message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Decimal(1, 2, 'decimal message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.decimal' => 'decimal message']
        );

        $this->assertFailsWithMessage(
            ['v' => '1.234'],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.decimal' => 'decimal message'],
            'v',
            'decimal message'
        );
    }
}
