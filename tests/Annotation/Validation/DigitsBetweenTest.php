<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\DigitsBetween;

/**
 * @internal
 * @coversNothing
 */
class DigitsBetweenTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new DigitsBetween(2, 4, 'digits_between message');

        $this->assertPasses(
            ['v' => '12'],
            ['v' => [$annotation->getRule()]],
            ['v.digits_between' => 'digits_between message']
        );

        $this->assertPasses(
            ['v' => '1234'],
            ['v' => [$annotation->getRule()]],
            ['v.digits_between' => 'digits_between message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new DigitsBetween(2, 4, 'digits_between message');

        $this->assertFailsWithMessage(
            ['v' => '1'],
            ['v' => [$annotation->getRule()]],
            ['v.digits_between' => 'digits_between message'],
            'v',
            'digits_between message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new DigitsBetween(1, 1, 'digits_between message');

        $this->assertPasses(
            ['v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.digits_between' => 'digits_between message']
        );

        $this->assertPasses(
            ['v' => 0],
            ['v' => [$annotation->getRule()]],
            ['v.digits_between' => 'digits_between message']
        );

        $this->assertFailsWithMessage(
            ['v' => null],
            ['v' => [$annotation->getRule()]],
            ['v.digits_between' => 'digits_between message'],
            'v',
            'digits_between message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new DigitsBetween(2, 3, 'digits_between message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.digits_between' => 'digits_between message']
        );

        $this->assertFailsWithMessage(
            ['v' => '1'],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.digits_between' => 'digits_between message'],
            'v',
            'digits_between message'
        );
    }
}
