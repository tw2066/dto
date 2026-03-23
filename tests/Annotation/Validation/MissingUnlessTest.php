<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\MissingUnless;

/**
 * @internal
 * @coversNothing
 */
class MissingUnlessTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new MissingUnless('status', 1, 'missing_unless message');

        $this->assertPasses(
            ['status' => 2],
            ['field' => [$annotation->getRule()]],
            ['field.missing_unless' => 'missing_unless message']
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new MissingUnless('status', 1, 'missing_unless message');

        $this->assertPasses(
            [],
            ['field' => [$annotation->getRule()]],
            ['field.missing_unless' => 'missing_unless message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new MissingUnless('status', 1, 'missing_unless message');

        $this->assertPasses(
            [],
            ['field' => ['sometimes', $annotation->getRule()]],
            ['field.missing_unless' => 'missing_unless message']
        );
    }
}
