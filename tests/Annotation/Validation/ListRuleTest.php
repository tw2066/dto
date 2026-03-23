<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\ListRule;

/**
 * @internal
 * @coversNothing
 */
class ListRuleTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new ListRule('list message');

        $this->assertPasses(
            ['items' => ['a', 'b', 'c']],
            ['items' => [$annotation->getRule()]],
            ['items.list' => 'list message']
        );

        $this->assertPasses(
            ['items' => [1, 2, 3]],
            ['items' => [$annotation->getRule()]],
            ['items.list' => 'list message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new ListRule('list message');

        $this->assertFailsWithMessage(
            ['items' => ['a' => 1, 'b' => 2]],
            ['items' => [$annotation->getRule()]],
            ['items.list' => 'list message'],
            'items',
            'list message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new ListRule('list message');

        $this->assertPasses(
            ['items' => []],
            ['items' => [$annotation->getRule()]],
            ['items.list' => 'list message']
        );

        $this->assertPasses(
            ['items' => ''],
            ['items' => [$annotation->getRule()]],
            ['items.list' => 'list message']
        );

        $this->assertPasses(
            ['items' => [0, 2]],
            ['items' => [$annotation->getRule()]],
            ['items.list' => 'list message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new ListRule('list message');

        $this->assertPasses(
            [],
            ['items' => ['sometimes', $annotation->getRule()]],
            ['items.list' => 'list message']
        );

        $this->assertFailsWithMessage(
            ['items' => ['a' => 1, 'b' => 2]],
            ['items' => ['sometimes', $annotation->getRule()]],
            ['items.list' => 'list message'],
            'items',
            'list message'
        );
    }
}
