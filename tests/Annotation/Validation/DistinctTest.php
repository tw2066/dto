<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Distinct;

/**
 * @internal
 * @coversNothing
 */
class DistinctTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Distinct(false, 'distinct message');

        $this->assertPasses(
            ['items' => [1, 2, 3, 4, 5]],
            ['items' => [$annotation->getRule()]],
            ['items.distinct' => 'distinct message']
        );

        $this->assertPasses(
            ['items' => ['a', 'b', 'c']],
            ['items' => [$annotation->getRule()]],
            ['items.distinct' => 'distinct message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Distinct(false, 'distinct message');

        $this->assertFailsWithMessage(
            ['items' => ['a', 'b', 'b', 'c', 'd']],
            ['items.*' => [$annotation->getRule(), 'string']],
            ['items.*' => 'distinct message'],
            'items.*',
            'distinct message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Distinct(false, 'distinct message');

        $this->assertPasses(
            ['items' => []],
            ['items' => [$annotation->getRule()]],
            ['items.distinct' => 'distinct message']
        );

        $this->assertPasses(
            ['items' => ''],
            ['items' => [$annotation->getRule()]],
            ['items.distinct' => 'distinct message']
        );

        $this->assertPasses(
            ['items' => null],
            ['items' => [$annotation->getRule()]],
            ['items.distinct' => 'distinct message']
        );
    }

    public function testIgnoreCaseMode(): void
    {
        $annotation = new Distinct(true, 'distinct message');

        $this->assertPasses(
            ['items' => ['a', 'B', 'c']],
            ['items' => [$annotation->getRule()]],
            ['items.distinct' => 'distinct message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Distinct(false, 'distinct message');

        $this->assertPasses(
            [],
            ['items' => ['sometimes', $annotation->getRule()]],
            ['items.distinct' => 'distinct message']
        );

        $this->assertFailsWithMessage(
            ['items' => ['a', 'b', 'b', 'c', 'd']],
            ['items.*' => [$annotation->getRule(), 'string']],
            ['items.*' => 'distinct message'],
            'items.*',
            'distinct message'
        );
    }
}
