<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\RequiredIf;

/**
 * @internal
 * @coversNothing
 */
class RequiredIfTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new RequiredIf('type', 'admin', 'required if message');

        $this->assertPasses(
            ['type' => 'admin', 'name' => 'value'],
            ['name' => [$annotation->getRule()]],
            ['name.required_if' => 'required if message']
        );

        $this->assertPasses(
            ['type' => 'user'],
            ['name' => [$annotation->getRule()]],
            ['name.required_if' => 'required if message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new RequiredIf('type', 'admin', 'required if message');

        $this->assertFailsWithMessage(
            ['type' => 'admin'],
            ['name' => [$annotation->getRule()]],
            ['name.required_if' => 'required if message'],
            'name',
            'required if message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotationArray = new RequiredIf('role', ['admin', 'manager'], 'required if message');
        $this->assertPasses(
            ['role' => 'admin', 'name' => 'value'],
            ['name' => [$annotationArray->getRule()]],
            ['name.required_if' => 'required if message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new RequiredIf('type', 'admin', 'required if message');

        $this->assertPasses(
            ['type' => 'admin'],
            ['name' => ['sometimes', $annotation->getRule()]],
            ['name.required_if' => 'required if message']
        );
    }
}
