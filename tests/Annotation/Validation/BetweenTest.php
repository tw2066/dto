<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Between;

/**
 * @internal
 * @coversNothing
 */
class BetweenTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Between(1, 10, 'between message');

        $this->assertPasses(
            ['number' => 5],
            ['number' => [$annotation->getRule()]],
            ['number.between' => 'between message']
        );

        $this->assertPasses(
            ['number' => 1],
            ['number' => [$annotation->getRule()]],
            ['number.between' => 'between message']
        );

        $this->assertPasses(
            ['number' => 10],
            ['number' => [$annotation->getRule()]],
            ['number.between' => 'between message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Between(1, 10, 'between message');

        $this->assertFailsWithMessage(
            ['number' => 0],
            ['number' => ['integer', $annotation->getRule()]],
            ['number.between' => 'between message'],
            'number',
            'between message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Between(1, 10, 'between message');

        $this->assertPasses(
            ['number' => ''],
            ['number' => [$annotation->getRule()]],
            ['number.between' => 'between message']
        );

        $this->assertFailsWithMessage(
            ['number' => null],
            ['number' => [$annotation->getRule()]],
            ['number.between' => 'between message'],
            'number',
            'between message'
        );

        $this->assertFailsWithMessage(
            ['number' => 11],
            ['number' => ['numeric', $annotation->getRule()]],
            ['number.between' => 'between message'],
            'number',
            'between message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Between(1, 10, 'between message');

        $this->assertPasses(
            [],
            ['number' => ['sometimes', $annotation->getRule()]],
            ['number.between' => 'between message']
        );

        $this->assertFailsWithMessage(
            ['number' => 0],
            ['number' => ['integer', $annotation->getRule()]],
            ['number.between' => 'between message'],
            'number',
            'between message'
        );
    }
}
