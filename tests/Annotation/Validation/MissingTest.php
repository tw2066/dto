<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Missing;

/**
 * @internal
 * @coversNothing
 */
class MissingTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Missing('missing message');

        $this->assertPasses(
            [],
            ['v' => [$annotation->getRule()]],
            ['v.missing' => 'missing message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Missing('missing message');

        $this->assertFailsWithMessage(
            ['v' => 1],
            ['v' => [$annotation->getRule()]],
            ['v.missing' => 'missing message'],
            'v',
            'missing message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Missing('missing message');

        $this->assertFailsWithMessage(
            ['v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.missing' => 'missing message'],
            'v',
            'missing message'
        );

        $this->assertFailsWithMessage(
            ['v' => 0],
            ['v' => [$annotation->getRule()]],
            ['v.missing' => 'missing message'],
            'v',
            'missing message'
        );

        $this->assertFailsWithMessage(
            ['v' => null],
            ['v' => [$annotation->getRule()]],
            ['v.missing' => 'missing message'],
            'v',
            'missing message'
        );

        $this->assertFailsWithMessage(
            ['v' => []],
            ['v' => [$annotation->getRule()]],
            ['v.missing' => 'missing message'],
            'v',
            'missing message'
        );

        $this->assertFailsWithMessage(
            ['v' => (object) ['a' => 1]],
            ['v' => [$annotation->getRule()]],
            ['v.missing' => 'missing message'],
            'v',
            'missing message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Missing('missing message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.missing' => 'missing message']
        );

        $this->assertFailsWithMessage(
            ['v' => 1],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.missing' => 'missing message'],
            'v',
            'missing message'
        );
    }
}

