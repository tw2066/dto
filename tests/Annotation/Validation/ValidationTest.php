<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Validation;

/**
 * @internal
 * @coversNothing
 */
class ValidationTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Validation('required', 'required message');

        $this->assertPasses(
            ['name' => 'Hello'],
            ['name' => [$annotation->getRule()]],
            ['name.required' => 'required message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Validation('required', 'required message');

        $this->assertFailsWithMessage(
            [],
            ['name' => [$annotation->getRule()]],
            ['name.required' => 'required message'],
            'name',
            'required message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Validation('string', 'string message');

        $this->assertPasses(
            ['name' => ''],
            ['name' => [$annotation->getRule()]],
            ['name.string' => 'string message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Validation('required', 'required message');

        $this->assertPasses(
            [],
            ['name' => ['sometimes', $annotation->getRule()]],
            ['name.required' => 'required message']
        );
    }
}
