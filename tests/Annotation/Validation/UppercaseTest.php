<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Uppercase;

/**
 * @internal
 * @coversNothing
 */
class UppercaseTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Uppercase('uppercase message');

        $this->assertPasses(
            ['name' => 'HELLO'],
            ['name' => [$annotation->getRule()]],
            ['name.uppercase' => 'uppercase message']
        );

        $this->assertPasses(
            ['name' => 'WORLD'],
            ['name' => [$annotation->getRule()]],
            ['name.uppercase' => 'uppercase message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Uppercase('uppercase message');

        $this->assertFailsWithMessage(
            ['name' => 'Hello'],
            ['name' => [$annotation->getRule()]],
            ['name.uppercase' => 'uppercase message'],
            'name',
            'uppercase message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Uppercase('uppercase message');

        $this->assertPasses(
            ['name' => ''],
            ['name' => [$annotation->getRule()]],
            ['name.uppercase' => 'uppercase message']
        );

        $this->assertPasses(
            ['name' => 'A'],
            ['name' => [$annotation->getRule()]],
            ['name.uppercase' => 'uppercase message']
        );

        $this->assertFailsWithMessage(
            ['name' => 'a'],
            ['name' => [$annotation->getRule()]],
            ['name.uppercase' => 'uppercase message'],
            'name',
            'uppercase message'
        );

        $this->assertFailsWithMessage(
            ['name' => 'Hello World'],
            ['name' => [$annotation->getRule()]],
            ['name.uppercase' => 'uppercase message'],
            'name',
            'uppercase message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Uppercase('uppercase message');

        $this->assertPasses(
            [],
            ['name' => ['sometimes', $annotation->getRule()]],
            ['name.uppercase' => 'uppercase message']
        );
    }
}
