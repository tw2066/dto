<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Gte;

/**
 * @internal
 * @coversNothing
 */
class GteTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Gte(10);

        $this->assertPasses(
            ['num' => 10],
            ['num' => [$annotation->getRule()]],
            []
        );

        $this->assertPasses(
            ['num' => 11],
            ['num' => [$annotation->getRule()]],
            []
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Gte(10, 'gte message');

        $this->assertFailsWithMessage(
            ['num' => 9],
            ['num' => [$annotation->getRule()]],
            ['num.gte' => 'gte message'],
            'num',
            'gte message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Gte(10, 'gte message');

        $this->assertPasses(
            ['num' => ''],
            ['num' => [$annotation->getRule()]],
            ['num.gte' => 'gte message']
        );

        $this->assertFailsWithMessage(
            ['num' => 0],
            ['num' => [$annotation->getRule()]],
            ['num.gte' => 'gte message'],
            'num',
            'gte message'
        );

        $this->assertPasses(
            ['num' => null],
            ['num' => [$annotation->getRule()]],
            ['num.gte' => 'gte message']
        );

        $this->assertFailsWithMessage(
            ['num' => []],
            ['num' => [$annotation->getRule()]],
            ['num.gte' => 'gte message'],
            'num',
            'gte message'
        );

        $this->assertFailsWithMessage(
            ['num' => (object) ['a' => 1]],
            ['num' => [$annotation->getRule()]],
            ['num.gte' => 'gte message'],
            'num',
            'gte message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Gte(10, 'gte message');

        $this->assertPasses(
            [],
            ['num' => ['sometimes', $annotation->getRule()]],
            ['num.gte' => 'gte message']
        );

        $this->assertFailsWithMessage(
            ['num' => 9],
            ['num' => ['sometimes', $annotation->getRule()]],
            ['num.gte' => 'gte message'],
            'num',
            'gte message'
        );
    }
}
