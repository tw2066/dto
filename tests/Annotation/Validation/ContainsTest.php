<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Contains;

/**
 * @internal
 * @coversNothing
 */
class ContainsTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Contains(['a', 'b'], 'contains message');

        $this->assertPasses(
            ['v' => ['a', 'b', 'c']],
            ['v' => [$annotation->getRule()]],
            ['v.contains' => 'contains message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Contains(['a', 'b'], 'contains message');

        $this->assertFailsWithMessage(
            ['v' => ['a']],
            ['v' => [$annotation->getRule()]],
            ['v.contains' => 'contains message'],
            'v',
            'contains message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Contains(['a'], 'contains message');

        $this->assertPasses(
            ['v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.contains' => 'contains message']
        );

        $this->assertPasses(
            ['v' => ['a']],
            ['v' => [$annotation->getRule()]],
            ['v.contains' => 'contains message']
        );

        $this->assertFailsWithMessage(
            ['v' => null],
            ['v' => [$annotation->getRule()]],
            ['v.contains' => 'contains message'],
            'v',
            'contains message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Contains(['a'], 'contains message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.contains' => 'contains message']
        );

        $this->assertFailsWithMessage(
            ['v' => []],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.contains' => 'contains message'],
            'v',
            'contains message'
        );
    }
}
