<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Filled;

/**
 * @internal
 * @coversNothing
 */
class FilledTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Filled('filled message');

        $this->assertPasses(
            ['v' => 'a'],
            ['v' => [$annotation->getRule()]],
            ['v.filled' => 'filled message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Filled('filled message');

        $this->assertFailsWithMessage(
            ['v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.filled' => 'filled message'],
            'v',
            'filled message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Filled('filled message');

        $this->assertPasses(
            [],
            ['v' => [$annotation->getRule()]],
            ['v.filled' => 'filled message']
        );

        $this->assertPasses(
            ['v' => 0],
            ['v' => [$annotation->getRule()]],
            ['v.filled' => 'filled message']
        );

        $this->assertFailsWithMessage(
            ['v' => null],
            ['v' => [$annotation->getRule()]],
            ['v.filled' => 'filled message'],
            'v',
            'filled message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Filled('filled message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.filled' => 'filled message']
        );

        $this->assertFailsWithMessage(
            ['v' => ''],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.filled' => 'filled message'],
            'v',
            'filled message'
        );
    }
}
