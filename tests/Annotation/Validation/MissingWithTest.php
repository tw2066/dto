<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\MissingWith;

/**
 * @internal
 * @coversNothing
 */
class MissingWithTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new MissingWith(['other_field'], 'missing_with message');

        $this->assertPasses(
            [],
            ['field' => [$annotation->getRule()]],
            ['field.missing_with' => 'missing_with message']
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new MissingWith(['other_field'], 'missing_with message');

        $this->assertPasses(
            ['another_field' => 'value'],
            ['field' => [$annotation->getRule()]],
            ['field.missing_with' => 'missing_with message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new MissingWith(['other_field'], 'missing_with message');

        $this->assertPasses(
            [],
            ['field' => ['sometimes', $annotation->getRule()]],
            ['field.missing_with' => 'missing_with message']
        );
    }
}
