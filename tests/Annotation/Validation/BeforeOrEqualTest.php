<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\BeforeOrEqual;

/**
 * @internal
 * @coversNothing
 */
class BeforeOrEqualTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new BeforeOrEqual('2020-01-01', 'before_or_equal message');

        $this->assertPasses(
            ['v' => '2019-12-31'],
            ['v' => [$annotation->getRule()]],
            ['v.before_or_equal' => 'before_or_equal message']
        );

        $this->assertPasses(
            ['v' => '2020-01-01'],
            ['v' => [$annotation->getRule()]],
            ['v.before_or_equal' => 'before_or_equal message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new BeforeOrEqual('2020-01-01', 'before_or_equal message');

        $this->assertFailsWithMessage(
            ['v' => '2020-01-02'],
            ['v' => [$annotation->getRule()]],
            ['v.before_or_equal' => 'before_or_equal message'],
            'v',
            'before_or_equal message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new BeforeOrEqual('2020-01-01', 'before_or_equal message');

        $this->assertPasses(
            ['v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.before_or_equal' => 'before_or_equal message']
        );

        $this->assertFailsWithMessage(
            ['v' => null],
            ['v' => [$annotation->getRule()]],
            ['v.before_or_equal' => 'before_or_equal message'],
            'v',
            'before_or_equal message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new BeforeOrEqual('2020-01-01', 'before_or_equal message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.before_or_equal' => 'before_or_equal message']
        );

        $this->assertFailsWithMessage(
            ['v' => '2020-01-02'],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.before_or_equal' => 'before_or_equal message'],
            'v',
            'before_or_equal message'
        );
    }
}
