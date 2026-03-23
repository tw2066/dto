<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\RequiredWithAll;

/**
 * @internal
 * @coversNothing
 */
class RequiredWithAllTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new RequiredWithAll(['field1', 'field2'], 'required with all message');

        $this->assertPasses(
            ['field1' => 'value1', 'field2' => 'value2', 'name' => 'value'],
            ['name' => [$annotation->getRule()]],
            ['name.required_with_all' => 'required with all message']
        );

        $this->assertPasses(
            [],
            ['name' => [$annotation->getRule()]],
            ['name.required_with_all' => 'required with all message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new RequiredWithAll(['field1', 'field2'], 'required with all message');

        $this->assertFailsWithMessage(
            ['field1' => 'value1', 'field2' => 'value2'],
            ['name' => [$annotation->getRule()]],
            ['name.required_with_all' => 'required with all message'],
            'name',
            'required with all message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new RequiredWithAll(['field1'], 'required with all message');

        $this->assertPasses(
            ['field1' => null, 'name' => 'value'],
            ['name' => [$annotation->getRule()]],
            ['name.required_with_all' => 'required with all message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new RequiredWithAll(['field1', 'field2'], 'required with all message');

        $this->assertPasses(
            ['field1' => 'value1', 'field2' => 'value2'],
            ['name' => ['sometimes', $annotation->getRule()]],
            ['name.required_with_all' => 'required with all message']
        );
    }
}
