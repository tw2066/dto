<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\DoesntEndWith;

/**
 * @internal
 * @coversNothing
 */
class DoesntEndWithTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new DoesntEndWith('c', 'doesnt_end_with message');

        $this->assertPasses(
            ['v' => 'abd'],
            ['v' => [$annotation->getRule()]],
            ['v.doesnt_end_with' => 'doesnt_end_with message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new DoesntEndWith('c', 'doesnt_end_with message');

        $this->assertFailsWithMessage(
            ['v' => 'abc'],
            ['v' => [$annotation->getRule()]],
            ['v.doesnt_end_with' => 'doesnt_end_with message'],
            'v',
            'doesnt_end_with message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new DoesntEndWith('c', 'doesnt_end_with message');

        $this->assertPasses(
            ['v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.doesnt_end_with' => 'doesnt_end_with message']
        );

        $this->assertPasses(
            ['v' => null],
            ['v' => ['nullable', $annotation->getRule()]],
            ['v.doesnt_end_with' => 'doesnt_end_with message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new DoesntEndWith('c', 'doesnt_end_with message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.doesnt_end_with' => 'doesnt_end_with message']
        );

        $this->assertFailsWithMessage(
            ['v' => 'abc'],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.doesnt_end_with' => 'doesnt_end_with message'],
            'v',
            'doesnt_end_with message'
        );
    }
}
