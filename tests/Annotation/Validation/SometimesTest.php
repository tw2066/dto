<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Sometimes;

/**
 * @internal
 * @coversNothing
 */
class SometimesTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Sometimes('sometimes message');

        $this->assertPasses(
            [],
            ['name' => [$annotation->getRule(), 'string']],
            ['name.sometimes' => 'sometimes message']
        );

        $this->assertPasses(
            ['name' => 'Hello'],
            ['name' => [$annotation->getRule(), 'string']],
            ['name.sometimes' => 'sometimes message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Sometimes('sometimes message');

        $this->assertFailsWithMessage(
            ['name' => 123],
            ['name' => [$annotation->getRule(), 'string']],
            ['name.string' => 'The name must be a string.'],
            'name',
            'The name must be a string.'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Sometimes('sometimes message');

        $this->assertPasses(
            [],
            ['name' => [$annotation->getRule(), 'string']],
            ['name.sometimes' => 'sometimes message']
        );

        $this->assertPasses(
            ['name' => ''],
            ['name' => [$annotation->getRule(), 'string']],
            ['name.sometimes' => 'sometimes message']
        );

        $this->assertPasses(
            ['name' => null],
            ['name' => [$annotation->getRule(), 'nullable', 'string']],
            ['name.sometimes' => 'sometimes message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Sometimes('sometimes message');

        $this->assertPasses(
            [],
            ['name' => [$annotation->getRule(), 'string']],
            ['name.sometimes' => 'sometimes message']
        );
    }
}
