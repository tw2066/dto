<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Lte;

/**
 * @internal
 * @coversNothing
 */
class LteTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Lte(10);

        $this->assertPasses(
            ['num' => 10],
            ['num' => [$annotation->getRule()]],
            []
        );

        $this->assertPasses(
            ['num' => 9],
            ['num' => [$annotation->getRule()]],
            []
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Lte(10, 'lte message');

        $this->assertFailsWithMessage(
            ['num' => 11],
            ['num' => [$annotation->getRule()]],
            ['num.lte' => 'lte message'],
            'num',
            'lte message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Lte(10, 'lte message');

        $this->assertPasses(
            ['num' => ''],
            ['num' => [$annotation->getRule()]],
            ['num.lte' => 'lte message']
        );

        $this->assertPasses(
            ['num' => 0],
            ['num' => [$annotation->getRule()]],
            ['num.lte' => 'lte message']
        );

        $this->assertPasses(
            ['num' => null],
            ['num' => [$annotation->getRule()]],
            ['num.lte' => 'lte message']
        );

        $this->assertFailsWithMessage(
            ['num' => []],
            ['num' => [$annotation->getRule()]],
            ['num.lte' => 'lte message'],
            'num',
            'lte message'
        );

        $this->assertFailsWithMessage(
            ['num' => (object) ['a' => 1]],
            ['num' => [$annotation->getRule()]],
            ['num.lte' => 'lte message'],
            'num',
            'lte message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Lte(10, 'lte message');

        $this->assertPasses(
            [],
            ['num' => ['sometimes', $annotation->getRule()]],
            ['num.lte' => 'lte message']
        );

        $this->assertFailsWithMessage(
            ['num' => 11],
            ['num' => ['sometimes', $annotation->getRule()]],
            ['num.lte' => 'lte message'],
            'num',
            'lte message'
        );
    }
}
