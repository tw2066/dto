<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\AfterOrEqual;

/**
 * @internal
 * @coversNothing
 */
class AfterOrEqualTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new AfterOrEqual('2020-01-01', 'after_or_equal message');

        $this->assertPasses(
            ['v' => '2020-01-01'],
            ['v' => [$annotation->getRule()]],
            ['v.after_or_equal' => 'after_or_equal message']
        );

        $this->assertPasses(
            ['v' => '2020-01-02'],
            ['v' => [$annotation->getRule()]],
            ['v.after_or_equal' => 'after_or_equal message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new AfterOrEqual('2020-01-01', 'after_or_equal message');

        $this->assertFailsWithMessage(
            ['v' => '2019-12-31'],
            ['v' => [$annotation->getRule()]],
            ['v.after_or_equal' => 'after_or_equal message'],
            'v',
            'after_or_equal message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new AfterOrEqual('2020-01-01', 'after_or_equal message');

        $this->assertPasses(
            ['v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.after_or_equal' => 'after_or_equal message']
        );

        $this->assertFailsWithMessage(
            ['v' => null],
            ['v' => [$annotation->getRule()]],
            ['v.after_or_equal' => 'after_or_equal message'],
            'v',
            'after_or_equal message'
        );

        $this->assertFailsWithMessage(
            ['v' => []],
            ['v' => [$annotation->getRule()]],
            ['v.after_or_equal' => 'after_or_equal message'],
            'v',
            'after_or_equal message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new AfterOrEqual('2020-01-01', 'after_or_equal message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.after_or_equal' => 'after_or_equal message']
        );

        $this->assertFailsWithMessage(
            ['v' => '2019-12-31'],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.after_or_equal' => 'after_or_equal message'],
            'v',
            'after_or_equal message'
        );
    }
}
