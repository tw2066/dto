<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\RequiredWithoutAll;

/**
 * @internal
 * @coversNothing
 */
class RequiredWithoutAllTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new RequiredWithoutAll(['field1', 'field2'], 'required without all message');

        $this->assertPasses(
            ['name' => 'value'],
            ['name' => [$annotation->getRule()]],
            ['name.required_without_all' => 'required without all message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new RequiredWithoutAll(['field1', 'field2'], 'required without all message');

        $this->assertFailsWithMessage(
            [],
            ['name' => [$annotation->getRule()]],
            ['name.required_without_all' => 'required without all message'],
            'name',
            'required without all message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new RequiredWithoutAll(['field1'], 'required without all message');

        $this->assertPasses(
            ['field1' => 'value', 'name' => 'value'],
            ['name' => [$annotation->getRule()]],
            ['name.required_without_all' => 'required without all message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new RequiredWithoutAll(['field1', 'field2'], 'required without all message');

        $this->assertPasses(
            ['field1' => 'value'],
            ['name' => ['sometimes', $annotation->getRule()]],
            ['name.required_without_all' => 'required without all message']
        );
    }
}
