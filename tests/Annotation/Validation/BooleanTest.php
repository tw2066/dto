<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Boolean;

/**
 * @internal
 * @coversNothing
 */
class BooleanTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Boolean(false, 'boolean message');

        $this->assertPasses(
            ['active' => true],
            ['active' => [$annotation->getRule()]],
            ['active.boolean' => 'boolean message']
        );

        $this->assertPasses(
            ['active' => false],
            ['active' => [$annotation->getRule()]],
            ['active.boolean' => 'boolean message']
        );

        $this->assertPasses(
            ['active' => 1],
            ['active' => [$annotation->getRule()]],
            ['active.boolean' => 'boolean message']
        );

        $this->assertPasses(
            ['active' => 0],
            ['active' => [$annotation->getRule()]],
            ['active.boolean' => 'boolean message']
        );

        $this->assertPasses(
            ['active' => '1'],
            ['active' => [$annotation->getRule()]],
            ['active.boolean' => 'boolean message']
        );

        $this->assertPasses(
            ['active' => '0'],
            ['active' => [$annotation->getRule()]],
            ['active.boolean' => 'boolean message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Boolean(false, 'boolean message');

        $this->assertFailsWithMessage(
            ['active' => 'invalid'],
            ['active' => [$annotation->getRule()]],
            ['active.boolean' => 'boolean message'],
            'active',
            'boolean message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Boolean(false, 'boolean message');

        $this->assertPasses(
            ['active' => ''],
            ['active' => [$annotation->getRule()]],
            ['active.boolean' => 'boolean message']
        );

        $this->assertFailsWithMessage(
            ['active' => null],
            ['active' => [$annotation->getRule()]],
            ['active.boolean' => 'boolean message'],
            'active',
            'boolean message'
        );

        $this->assertFailsWithMessage(
            ['active' => 2],
            ['active' => [$annotation->getRule()]],
            ['active.boolean' => 'boolean message'],
            'active',
            'boolean message'
        );
    }

    public function testStrictMode(): void
    {
        $annotation = new Boolean(true, 'boolean message');

        $this->assertPasses(
            ['active' => true],
            ['active' => [$annotation->getRule()]],
            ['active.boolean' => 'boolean message']
        );

        $this->assertPasses(
            ['active' => false],
            ['active' => [$annotation->getRule()]],
            ['active.boolean' => 'boolean message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Boolean(false, 'boolean message');

        $this->assertPasses(
            [],
            ['active' => ['sometimes', $annotation->getRule()]],
            ['active.boolean' => 'boolean message']
        );

        $this->assertFailsWithMessage(
            ['active' => 'invalid'],
            ['active' => ['sometimes', $annotation->getRule()]],
            ['active.boolean' => 'boolean message'],
            'active',
            'boolean message'
        );
    }
}
