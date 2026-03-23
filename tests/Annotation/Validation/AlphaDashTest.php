<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\AlphaDash;

/**
 * @internal
 * @coversNothing
 */
class AlphaDashTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new AlphaDash('ascii', 'alpha_dash message');

        $this->assertPasses(
            ['name' => 'Hello_World'],
            ['name' => [$annotation->getRule()]],
            ['name.alpha_dash' => 'alpha_dash message']
        );

        $this->assertPasses(
            ['name' => 'Hello-World'],
            ['name' => [$annotation->getRule()]],
            ['name.alpha_dash' => 'alpha_dash message']
        );

        $this->assertPasses(
            ['name' => 'Hello123'],
            ['name' => [$annotation->getRule()]],
            ['name.alpha_dash' => 'alpha_dash message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new AlphaDash('ascii', 'alpha_dash message');

        $this->assertFailsWithMessage(
            ['name' => 'Hello World'],
            ['name' => [$annotation->getRule()]],
            ['name.alpha_dash' => 'alpha_dash message'],
            'name',
            'alpha_dash message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new AlphaDash('ascii', 'alpha_dash message');

        $this->assertPasses(
            ['name' => ''],
            ['name' => [$annotation->getRule()]],
            ['name.alpha_dash' => 'alpha_dash message']
        );

        $this->assertFailsWithMessage(
            ['name' => '&'],
            ['name' => [$annotation->getRule()]],
            ['name.alpha_dash' => 'alpha_dash message'],
            'name',
            'alpha_dash message'
        );

        $this->assertFailsWithMessage(
            ['name' => null],
            ['name' => [$annotation->getRule()]],
            ['name.alpha_dash' => 'alpha_dash message'],
            'name',
            'alpha_dash message'
        );

        $this->assertFailsWithMessage(
            ['name' => 'Hello!'],
            ['name' => [$annotation->getRule()]],
            ['name.alpha_dash' => 'alpha_dash message'],
            'name',
            'alpha_dash message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new AlphaDash('ascii', 'alpha_dash message');

        $this->assertPasses(
            [],
            ['name' => ['sometimes', $annotation->getRule()]],
            ['name.alpha_dash' => 'alpha_dash message']
        );

        $this->assertFailsWithMessage(
            ['name' => 'Hello World'],
            ['name' => ['sometimes', $annotation->getRule()]],
            ['name.alpha_dash' => 'alpha_dash message'],
            'name',
            'alpha_dash message'
        );
    }
}
