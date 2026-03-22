<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\MissingUnless;

/**
 * @internal
 * @coversNothing
 */
class MissingUnlessTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new MissingUnless('status', 1, 'missing_unless message');

        $this->assertPasses(
            ['status' => 1, 'v' => 1],
            ['v' => [$annotation->getRule()]],
            ['v.missing_unless' => 'missing_unless message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new MissingUnless('status', 1, 'missing_unless message');

        $this->assertFailsWithMessage(
            ['status' => 0, 'v' => 1],
            ['v' => [$annotation->getRule()]],
            ['v.missing_unless' => 'missing_unless message'],
            'v',
            'missing_unless message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new MissingUnless('status', 1, 'missing_unless message');

        $this->assertFailsWithMessage(
            ['status' => 0, 'v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.missing_unless' => 'missing_unless message'],
            'v',
            'missing_unless message'
        );

        $this->assertPasses(
            ['status' => 1, 'v' => null],
            ['v' => [$annotation->getRule()]],
            ['v.missing_unless' => 'missing_unless message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new MissingUnless('status', 1, 'missing_unless message');

        $this->assertPasses(
            ['status' => 1, 'v' => 1],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.missing_unless' => 'missing_unless message']
        );

        $this->assertFailsWithMessage(
            ['status' => 0, 'v' => 1],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.missing_unless' => 'missing_unless message'],
            'v',
            'missing_unless message'
        );
    }
}

