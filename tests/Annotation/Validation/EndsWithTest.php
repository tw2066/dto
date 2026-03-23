<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\EndsWith;

/**
 * @internal
 * @coversNothing
 */
class EndsWithTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new EndsWith('c', 'ends_with message');

        $this->assertPasses(
            ['v' => 'abc'],
            ['v' => [$annotation->getRule()]],
            ['v.ends_with' => 'ends_with message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new EndsWith('c', 'ends_with message');

        $this->assertFailsWithMessage(
            ['v' => 'abd'],
            ['v' => [$annotation->getRule()]],
            ['v.ends_with' => 'ends_with message'],
            'v',
            'ends_with message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new EndsWith('c', 'ends_with message');

        $this->assertPasses(
            ['v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.ends_with' => 'ends_with message']
        );

        $this->assertPasses(
            ['v' => null],
            ['v' => ['nullable', $annotation->getRule()]],
            ['v.ends_with' => 'ends_with message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new EndsWith('c', 'ends_with message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.ends_with' => 'ends_with message']
        );

        $this->assertFailsWithMessage(
            ['v' => 'abd'],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.ends_with' => 'ends_with message'],
            'v',
            'ends_with message'
        );
    }
}
