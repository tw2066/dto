<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Prohibits;

/**
 * @internal
 * @coversNothing
 */
class ProhibitsTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Prohibits(['other_field'], 'prohibits message');

        $this->assertPasses(
            [],
            ['field' => [$annotation->getRule()]],
            ['field.prohibits' => 'prohibits message']
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Prohibits(['other_field'], 'prohibits message');

        $this->assertPasses(
            ['field' => ''],
            ['field' => [$annotation->getRule()]],
            ['field.prohibits' => 'prohibits message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Prohibits(['other_field'], 'prohibits message');

        $this->assertPasses(
            [],
            ['field' => ['sometimes', $annotation->getRule()]],
            ['field.prohibits' => 'prohibits message']
        );
    }
}
