<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Required;

/**
 * @internal
 * @coversNothing
 */
class RequiredTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Required('required message');

        $this->assertPasses(
            ['field' => 'value'],
            ['field' => [$annotation->getRule()]],
            ['field.required' => 'required message']
        );

        $this->assertPasses(
            ['field' => ['a', 'b']],
            ['field' => [$annotation->getRule()]],
            ['field.required' => 'required message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Required('required message');

        $this->assertFailsWithMessage(
            ['field' => ''],
            ['field' => [$annotation->getRule()]],
            ['field.required' => 'required message'],
            'field',
            'required message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Required('required message');

        $this->assertFailsWithMessage(
            ['field' => null],
            ['field' => [$annotation->getRule()]],
            ['field.required' => 'required message'],
            'field',
            'required message'
        );

        $this->assertFailsWithMessage(
            [],
            ['field' => [$annotation->getRule()]],
            ['field.required' => 'required message'],
            'field',
            'required message'
        );

        $this->assertFailsWithMessage(
            ['field' => []],
            ['field' => [$annotation->getRule()]],
            ['field.required' => 'required message'],
            'field',
            'required message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Required('required message');

        $this->assertPasses(
            [],
            ['field' => ['sometimes', $annotation->getRule()]],
            ['field.required' => 'required message']
        );

        $this->assertFailsWithMessage(
            ['field' => ''],
            ['field' => ['sometimes', $annotation->getRule()]],
            ['field.required' => 'required message'],
            'field',
            'required message'
        );
    }
}
