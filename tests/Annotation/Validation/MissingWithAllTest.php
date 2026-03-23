<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\MissingWithAll;

/**
 * @internal
 * @coversNothing
 */
class MissingWithAllTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new MissingWithAll(['field1', 'field2'], 'missing_with_all message');

        $this->assertPasses(
            [],
            ['field' => [$annotation->getRule()]],
            ['field.missing_with_all' => 'missing_with_all message']
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new MissingWithAll(['field1', 'field2'], 'missing_with_all message');

        $this->assertPasses(
            ['field1' => 'value'],
            ['field' => [$annotation->getRule()]],
            ['field.missing_with_all' => 'missing_with_all message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new MissingWithAll(['field1', 'field2'], 'missing_with_all message');

        $this->assertPasses(
            [],
            ['field' => ['sometimes', $annotation->getRule()]],
            ['field.missing_with_all' => 'missing_with_all message']
        );
    }
}
