<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\RequiredWith;

/**
 * @internal
 * @coversNothing
 */
class RequiredWithTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new RequiredWith(['field1', 'field2'], 'required with message');

        $this->assertPasses(
            ['field1' => 'value', 'name' => 'value'],
            ['name' => [$annotation->getRule()]],
            ['name.required_with' => 'required with message']
        );

        $this->assertPasses(
            [],
            ['name' => [$annotation->getRule()]],
            ['name.required_with' => 'required with message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new RequiredWith(['field1', 'field2'], 'required with message');

        $this->assertFailsWithMessage(
            ['field1' => 'value'],
            ['name' => [$annotation->getRule()]],
            ['name.required_with' => 'required with message'],
            'name',
            'required with message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new RequiredWith(['field1'], 'required with message');

        $this->assertPasses(
            ['field1' => null, 'name' => 'value'],
            ['name' => [$annotation->getRule()]],
            ['name.required_with' => 'required with message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new RequiredWith(['field1', 'field2'], 'required with message');

        $this->assertPasses(
            ['field1' => 'value'],
            ['name' => ['sometimes', $annotation->getRule()]],
            ['name.required_with' => 'required with message']
        );
    }
}
