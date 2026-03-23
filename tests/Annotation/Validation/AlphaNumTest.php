<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\AlphaNum;

/**
 * @internal
 * @coversNothing
 */
class AlphaNumTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new AlphaNum('ascii', 'alpha_num message');

        $this->assertPasses(
            ['name' => 'Hello'],
            ['name' => [$annotation->getRule()]],
            ['name.alpha_num' => 'alpha_num message']
        );

        $this->assertPasses(
            ['name' => 'Hello123'],
            ['name' => [$annotation->getRule()]],
            ['name.alpha_num' => 'alpha_num message']
        );

        $this->assertPasses(
            ['name' => '123'],
            ['name' => [$annotation->getRule()]],
            ['name.alpha_num' => 'alpha_num message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new AlphaNum('ascii', 'alpha_num message');

        $this->assertFailsWithMessage(
            ['name' => 'Hello World'],
            ['name' => [$annotation->getRule()]],
            ['name.alpha_num' => 'alpha_num message'],
            'name',
            'alpha_num message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new AlphaNum('ascii', 'alpha_num message');

        $this->assertPasses(
            ['name' => ''],
            ['name' => [$annotation->getRule()]],
            ['name.alpha_num' => 'alpha_num message']
        );

        $this->assertFailsWithMessage(
            ['name' => '_'],
            ['name' => [$annotation->getRule()]],
            ['name.alpha_num' => 'alpha_num message'],
            'name',
            'alpha_num message'
        );

        $this->assertFailsWithMessage(
            ['name' => null],
            ['name' => [$annotation->getRule()]],
            ['name.alpha_num' => 'alpha_num message'],
            'name',
            'alpha_num message'
        );

        $this->assertFailsWithMessage(
            ['name' => 'Hello_World'],
            ['name' => [$annotation->getRule()]],
            ['name.alpha_num' => 'alpha_num message'],
            'name',
            'alpha_num message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new AlphaNum('ascii', 'alpha_num message');

        $this->assertPasses(
            [],
            ['name' => ['sometimes', $annotation->getRule()]],
            ['name.alpha_num' => 'alpha_num message']
        );

        $this->assertFailsWithMessage(
            ['name' => 'Hello World'],
            ['name' => ['sometimes', $annotation->getRule()]],
            ['name.alpha_num' => 'alpha_num message'],
            'name',
            'alpha_num message'
        );
    }
}
