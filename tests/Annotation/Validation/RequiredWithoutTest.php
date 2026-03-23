<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\RequiredWithout;

/**
 * @internal
 * @coversNothing
 */
class RequiredWithoutTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new RequiredWithout(['field1', 'field2'], 'required without message');

        $this->assertPasses(
            ['name' => 'value'],
            ['name' => [$annotation->getRule()]],
            ['name.required_without' => 'required without message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new RequiredWithout(['field1', 'field2'], 'required without message');

        $this->assertFailsWithMessage(
            [],
            ['name' => [$annotation->getRule()]],
            ['name.required_without' => 'required without message'],
            'name',
            'required without message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new RequiredWithout(['field1'], 'required without message');

        $this->assertPasses(
            ['field1' => 'value'],
            ['name' => [$annotation->getRule()]],
            ['name.required_without' => 'required without message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new RequiredWithout(['field1', 'field2'], 'required without message');

        $this->assertPasses(
            [],
            ['name' => ['sometimes', $annotation->getRule()]],
            ['name.required_without' => 'required without message']
        );
    }
}
