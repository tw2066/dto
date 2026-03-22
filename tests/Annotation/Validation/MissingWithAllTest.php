<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\MissingWithAll;

/**
 * @internal
 * @coversNothing
 */
class MissingWithAllTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new MissingWithAll(['a', 'b'], 'missing_with_all message');

        $this->assertPasses(
            ['a' => 1],
            ['v' => [$annotation->getRule()]],
            ['v.missing_with_all' => 'missing_with_all message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new MissingWithAll(['a', 'b'], 'missing_with_all message');

        $this->assertFailsWithMessage(
            ['a' => 1, 'b' => 1, 'v' => 1],
            ['v' => [$annotation->getRule()]],
            ['v.missing_with_all' => 'missing_with_all message'],
            'v',
            'missing_with_all message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new MissingWithAll(['a', 'b'], 'missing_with_all message');

        $this->assertFailsWithMessage(
            ['a' => 1, 'b' => 1, 'v' => null],
            ['v' => [$annotation->getRule()]],
            ['v.missing_with_all' => 'missing_with_all message'],
            'v',
            'missing_with_all message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new MissingWithAll(['a', 'b'], 'missing_with_all message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.missing_with_all' => 'missing_with_all message']
        );

        $this->assertFailsWithMessage(
            ['a' => 1, 'b' => 1, 'v' => 1],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.missing_with_all' => 'missing_with_all message'],
            'v',
            'missing_with_all message'
        );
    }
}

