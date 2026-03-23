<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Different;

/**
 * @internal
 * @coversNothing
 */
class DifferentTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Different('other', 'different message');

        $this->assertPasses(
            ['v' => 'a', 'other' => 'b'],
            ['v' => [$annotation->getRule()]],
            ['v.different' => 'different message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Different('other', 'different message');

        $this->assertFailsWithMessage(
            ['v' => 'a', 'other' => 'a'],
            ['v' => [$annotation->getRule()]],
            ['v.different' => 'different message'],
            'v',
            'different message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Different('other', 'different message');

        $this->assertPasses(
            ['v' => '', 'other' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.different' => 'different message']
        );

        $this->assertPasses(
            ['v' => null, 'other' => 'a'],
            ['v' => [$annotation->getRule()]],
            ['v.different' => 'different message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Different('other', 'different message');

        $this->assertPasses(
            ['other' => 'a'],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.different' => 'different message']
        );

        $this->assertFailsWithMessage(
            ['v' => 'a', 'other' => 'a'],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.different' => 'different message'],
            'v',
            'different message'
        );
    }
}
