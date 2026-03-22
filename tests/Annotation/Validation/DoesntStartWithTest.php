<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\DoesntStartWith;

/**
 * @internal
 * @coversNothing
 */
class DoesntStartWithTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new DoesntStartWith('a', 'doesnt_start_with message');

        $this->assertPasses(
            ['v' => 'bcd'],
            ['v' => [$annotation->getRule()]],
            ['v.doesnt_start_with' => 'doesnt_start_with message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new DoesntStartWith('a', 'doesnt_start_with message');

        $this->assertFailsWithMessage(
            ['v' => 'abc'],
            ['v' => [$annotation->getRule()]],
            ['v.doesnt_start_with' => 'doesnt_start_with message'],
            'v',
            'doesnt_start_with message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new DoesntStartWith('a', 'doesnt_start_with message');

        $this->assertPasses(
            ['v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.doesnt_start_with' => 'doesnt_start_with message']
        );

        $this->assertPasses(
            ['v' => null],
            ['v' => ['nullable', $annotation->getRule()]],
            ['v.doesnt_start_with' => 'doesnt_start_with message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new DoesntStartWith('a', 'doesnt_start_with message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.doesnt_start_with' => 'doesnt_start_with message']
        );

        $this->assertFailsWithMessage(
            ['v' => 'abc'],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.doesnt_start_with' => 'doesnt_start_with message'],
            'v',
            'doesnt_start_with message'
        );
    }
}
