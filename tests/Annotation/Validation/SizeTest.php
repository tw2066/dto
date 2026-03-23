<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Size;

/**
 * @internal
 * @coversNothing
 */
class SizeTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Size(5, 'size message');

        $this->assertPasses(
            ['name' => 'Hello'],
            ['name' => [$annotation->getRule()]],
            ['name.size' => 'size message']
        );

        $this->assertPasses(
            ['numbers' => [1, 2, 3, 4, 5]],
            ['numbers' => [$annotation->getRule()]],
            ['numbers.size' => 'size message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Size(5, 'size message');

        $this->assertFailsWithMessage(
            ['name' => 'Hello World'],
            ['name' => [$annotation->getRule()]],
            ['name.size' => 'size message'],
            'name',
            'size message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Size(0, 'size message');

        $this->assertPasses(
            ['name' => ''],
            ['name' => [$annotation->getRule()]],
            ['name.size' => 'size message']
        );

        $annotationFive = new Size(5, 'size message');
        $this->assertPasses(
            ['name' => '12345'],
            ['name' => [$annotationFive->getRule()]],
            ['name.size' => 'size message']
        );

        $this->assertFailsWithMessage(
            ['name' => '1234'],
            ['name' => [$annotationFive->getRule()]],
            ['name.size' => 'size message'],
            'name',
            'size message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Size(5, 'size message');

        $this->assertPasses(
            [],
            ['name' => ['sometimes', $annotation->getRule()]],
            ['name.size' => 'size message']
        );
    }
}
