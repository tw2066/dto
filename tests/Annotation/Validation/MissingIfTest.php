<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\MissingIf;

/**
 * @internal
 * @coversNothing
 */
class MissingIfTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new MissingIf('status', 1, 'missing_if message');

        $this->assertPasses(
            ['status' => 1],
            ['v' => [$annotation->getRule()]],
            ['v.missing_if' => 'missing_if message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new MissingIf('status', 1, 'missing_if message');

        $this->assertFailsWithMessage(
            ['status' => 1, 'v' => 1],
            ['v' => [$annotation->getRule()]],
            ['v.missing_if' => 'missing_if message'],
            'v',
            'missing_if message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new MissingIf('status', 1, 'missing_if message');

        $this->assertFailsWithMessage(
            ['status' => 1, 'v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.missing_if' => 'missing_if message'],
            'v',
            'missing_if message'
        );

        $this->assertPasses(
            ['status' => 0, 'v' => null],
            ['v' => [$annotation->getRule()]],
            ['v.missing_if' => 'missing_if message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new MissingIf('status', 1, 'missing_if message');

        $this->assertPasses(
            ['status' => 1],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.missing_if' => 'missing_if message']
        );

        $this->assertFailsWithMessage(
            ['status' => 1, 'v' => 1],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.missing_if' => 'missing_if message'],
            'v',
            'missing_if message'
        );
    }
}
