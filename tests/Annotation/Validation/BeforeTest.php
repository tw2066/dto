<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Before;

/**
 * @internal
 * @coversNothing
 */
class BeforeTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Before('2020-01-01', 'before message');

        $this->assertPasses(
            ['v' => '2019-12-31'],
            ['v' => [$annotation->getRule()]],
            ['v.before' => 'before message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Before('2020-01-01', 'before message');

        $this->assertFailsWithMessage(
            ['v' => '2020-01-01'],
            ['v' => [$annotation->getRule()]],
            ['v.before' => 'before message'],
            'v',
            'before message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Before('2020-01-01', 'before message');

        $this->assertPasses(
            ['v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.before' => 'before message']
        );

        $this->assertFailsWithMessage(
            ['v' => null],
            ['v' => [$annotation->getRule()]],
            ['v.before' => 'before message'],
            'v',
            'before message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Before('2020-01-01', 'before message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.before' => 'before message']
        );

        $this->assertFailsWithMessage(
            ['v' => '2020-01-01'],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.before' => 'before message'],
            'v',
            'before message'
        );
    }
}
