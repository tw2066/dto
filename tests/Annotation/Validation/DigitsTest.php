<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Digits;

/**
 * @internal
 * @coversNothing
 */
class DigitsTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Digits(4, 'digits message');

        $this->assertPasses(
            ['v' => '1234'],
            ['v' => [$annotation->getRule()]],
            ['v.digits' => 'digits message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Digits(4, 'digits message');

        $this->assertFailsWithMessage(
            ['v' => '123'],
            ['v' => [$annotation->getRule()]],
            ['v.digits' => 'digits message'],
            'v',
            'digits message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Digits(1, 'digits message');

        $this->assertPasses(
            ['v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.digits' => 'digits message']
        );

        $this->assertPasses(
            ['v' => 0],
            ['v' => [$annotation->getRule()]],
            ['v.digits' => 'digits message']
        );

        $this->assertFailsWithMessage(
            ['v' => null],
            ['v' => [$annotation->getRule()]],
            ['v.digits' => 'digits message'],
            'v',
            'digits message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Digits(2, 'digits message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.digits' => 'digits message']
        );

        $this->assertFailsWithMessage(
            ['v' => '1'],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.digits' => 'digits message'],
            'v',
            'digits message'
        );
    }
}
