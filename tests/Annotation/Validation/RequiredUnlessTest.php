<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\RequiredUnless;

/**
 * @internal
 * @coversNothing
 */
class RequiredUnlessTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new RequiredUnless('type', 'admin', 'required unless message');

        $this->assertPasses(
            ['type' => 'user', 'name' => 'value'],
            ['name' => [$annotation->getRule()]],
            ['name.required_unless' => 'required unless message']
        );

        $this->assertPasses(
            ['type' => 'admin'],
            ['name' => [$annotation->getRule()]],
            ['name.required_unless' => 'required unless message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new RequiredUnless('type', 'admin', 'required unless message');

        $this->assertFailsWithMessage(
            ['type' => 'user'],
            ['name' => [$annotation->getRule()]],
            ['name.required_unless' => 'required unless message'],
            'name',
            'required unless message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotationArray = new RequiredUnless('role', ['admin', 'manager'], 'required unless message');
        $this->assertPasses(
            ['role' => 'admin'],
            ['name' => [$annotationArray->getRule()]],
            ['name.required_unless' => 'required unless message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new RequiredUnless('type', 'admin', 'required unless message');

        $this->assertPasses(
            ['type' => 'user'],
            ['name' => ['sometimes', $annotation->getRule()]],
            ['name.required_unless' => 'required unless message']
        );
    }
}
