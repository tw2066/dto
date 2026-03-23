<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Alpha;

/**
 * @internal
 * @coversNothing
 */
class AlphaTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Alpha('ascii', 'alpha message');

        $this->assertPasses(
            ['name' => 'Hello'],
            ['name' => [$annotation->getRule()]],
            ['name.alpha' => 'alpha message']
        );

        $this->assertPasses(
            ['name' => 'World'],
            ['name' => [$annotation->getRule()]],
            ['name.alpha' => 'alpha message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Alpha('ascii', 'alpha message');

        $this->assertFailsWithMessage(
            ['name' => 'Hello123'],
            ['name' => [$annotation->getRule()]],
            ['name.alpha' => 'alpha message'],
            'name',
            'alpha message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Alpha('ascii', 'alpha message');

        $this->assertPasses(
            ['name' => ''],
            ['name' => [$annotation->getRule()]],
            ['name.alpha' => 'alpha message']
        );

        $this->assertFailsWithMessage(
            ['name' => 0],
            ['name' => [$annotation->getRule()]],
            ['name.alpha' => 'alpha message'],
            'name',
            'alpha message'
        );

        $this->assertFailsWithMessage(
            ['name' => null],
            ['name' => [$annotation->getRule()]],
            ['name.alpha' => 'alpha message'],
            'name',
            'alpha message'
        );

        $this->assertFailsWithMessage(
            ['name' => 'Hello World'],
            ['name' => [$annotation->getRule()]],
            ['name.alpha' => 'alpha message'],
            'name',
            'alpha message'
        );

        $this->assertFailsWithMessage(
            ['name' => 'Hello_World'],
            ['name' => [$annotation->getRule()]],
            ['name.alpha' => 'alpha message'],
            'name',
            'alpha message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Alpha('ascii', 'alpha message');

        $this->assertPasses(
            [],
            ['name' => ['sometimes', $annotation->getRule()]],
            ['name.alpha' => 'alpha message']
        );

        $this->assertFailsWithMessage(
            ['name' => 'Hello123'],
            ['name' => ['sometimes', $annotation->getRule()]],
            ['name.alpha' => 'alpha message'],
            'name',
            'alpha message'
        );
    }
}
