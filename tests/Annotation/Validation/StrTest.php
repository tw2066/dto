<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Str;

/**
 * @internal
 * @coversNothing
 */
class StrTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Str('string message');

        $this->assertPasses(
            ['name' => 'Hello'],
            ['name' => [$annotation->getRule()]],
            ['name.string' => 'string message']
        );

        $this->assertPasses(
            ['name' => ''],
            ['name' => [$annotation->getRule()]],
            ['name.string' => 'string message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Str('string message');

        $this->assertFailsWithMessage(
            ['name' => 123],
            ['name' => [$annotation->getRule()]],
            ['name.string' => 'string message'],
            'name',
            'string message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Str('string message');

        $this->assertPasses(
            ['name' => ''],
            ['name' => [$annotation->getRule()]],
            ['name.string' => 'string message']
        );

        $this->assertPasses(
            ['name' => '0'],
            ['name' => [$annotation->getRule()]],
            ['name.string' => 'string message']
        );

        $this->assertFailsWithMessage(
            ['name' => null],
            ['name' => [$annotation->getRule()]],
            ['name.string' => 'string message'],
            'name',
            'string message'
        );

        $this->assertFailsWithMessage(
            ['name' => []],
            ['name' => [$annotation->getRule()]],
            ['name.string' => 'string message'],
            'name',
            'string message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Str('string message');

        $this->assertPasses(
            [],
            ['name' => ['sometimes', $annotation->getRule()]],
            ['name.string' => 'string message']
        );
    }
}
