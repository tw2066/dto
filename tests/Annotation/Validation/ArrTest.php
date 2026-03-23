<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Arr;

/**
 * @internal
 * @coversNothing
 */
class ArrTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Arr('', 'array message');

        $this->assertPasses(
            ['items' => ['a', 'b', 'c']],
            ['items' => [$annotation->getRule()]],
            ['items.array' => 'array message']
        );

        $this->assertPasses(
            ['items' => []],
            ['items' => [$annotation->getRule()]],
            ['items.array' => 'array message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Arr('', 'array message');

        $this->assertFailsWithMessage(
            ['items' => 'not an array'],
            ['items' => [$annotation->getRule()]],
            ['items.array' => 'array message'],
            'items',
            'array message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Arr('', 'array message');

        $this->assertPasses(
            ['items' => ''],
            ['items' => [$annotation->getRule()]],
            ['items.array' => 'array message']
        );

        $this->assertFailsWithMessage(
            ['items' => 0],
            ['items' => [$annotation->getRule()]],
            ['items.array' => 'array message'],
            'items',
            'array message'
        );

        $this->assertFailsWithMessage(
            ['items' => null],
            ['items' => [$annotation->getRule()]],
            ['items.array' => 'array message'],
            'items',
            'array message'
        );

        $this->assertFailsWithMessage(
            ['items' => (object) ['a' => 1]],
            ['items' => [$annotation->getRule()]],
            ['items.array' => 'array message'],
            'items',
            'array message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Arr('', 'array message');

        $this->assertPasses(
            [],
            ['items' => ['sometimes', $annotation->getRule()]],
            ['items.array' => 'array message']
        );

        $this->assertFailsWithMessage(
            ['items' => 'not an array'],
            ['items' => ['sometimes', $annotation->getRule()]],
            ['items.array' => 'array message'],
            'items',
            'array message'
        );
    }
}
