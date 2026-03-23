<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Present;

/**
 * @internal
 * @coversNothing
 */
class PresentTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Present('present message');

        $this->assertPasses(
            ['field' => ''],
            ['field' => [$annotation->getRule()]],
            ['field.present' => 'present message']
        );

        $this->assertPasses(
            ['field' => 'value'],
            ['field' => [$annotation->getRule()]],
            ['field.present' => 'present message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Present('present message');

        $this->assertFailsWithMessage(
            [],
            ['field' => [$annotation->getRule()]],
            ['field.present' => 'present message'],
            'field',
            'present message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Present('present message');

        $this->assertPasses(
            ['field' => null],
            ['field' => [$annotation->getRule()]],
            ['field.present' => 'present message']
        );

        $this->assertPasses(
            ['field' => []],
            ['field' => [$annotation->getRule()]],
            ['field.present' => 'present message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Present('present message');

        $this->assertPasses(
            [],
            ['field' => ['sometimes', $annotation->getRule()]],
            ['field.present' => 'present message']
        );
    }
}
