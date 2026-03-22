<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\MissingWith;

/**
 * @internal
 * @coversNothing
 */
class MissingWithTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new MissingWith(['a'], 'missing_with message');

        $this->assertPasses(
            [],
            ['v' => [$annotation->getRule()]],
            ['v.missing_with' => 'missing_with message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new MissingWith(['a'], 'missing_with message');

        $this->assertFailsWithMessage(
            ['a' => 1, 'v' => 1],
            ['v' => [$annotation->getRule()]],
            ['v.missing_with' => 'missing_with message'],
            'v',
            'missing_with message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new MissingWith(['a'], 'missing_with message');

        $this->assertFailsWithMessage(
            ['a' => 1, 'v' => null],
            ['v' => [$annotation->getRule()]],
            ['v.missing_with' => 'missing_with message'],
            'v',
            'missing_with message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new MissingWith(['a'], 'missing_with message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.missing_with' => 'missing_with message']
        );

        $this->assertFailsWithMessage(
            ['a' => 1, 'v' => 1],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.missing_with' => 'missing_with message'],
            'v',
            'missing_with message'
        );
    }
}

