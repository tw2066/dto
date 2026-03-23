<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Nullable;

/**
 * @internal
 * @coversNothing
 */
class NullableTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Nullable('nullable message');

        $this->assertPasses(
            ['field' => null],
            ['field' => [$annotation->getRule()]],
            ['field.nullable' => 'nullable message']
        );

        $this->assertPasses(
            ['field' => 'value'],
            ['field' => [$annotation->getRule()]],
            ['field.nullable' => 'nullable message']
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Nullable('nullable message');

        $this->assertPasses(
            ['field' => ''],
            ['field' => [$annotation->getRule()]],
            ['field.nullable' => 'nullable message']
        );

        $this->assertPasses(
            [],
            ['field' => [$annotation->getRule()]],
            ['field.nullable' => 'nullable message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Nullable('nullable message');

        $this->assertPasses(
            [],
            ['field' => ['sometimes', $annotation->getRule()]],
            ['field.nullable' => 'nullable message']
        );
    }
}
