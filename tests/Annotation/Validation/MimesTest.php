<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Mimes;

/**
 * @internal
 * @coversNothing
 */
class MimesTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Mimes(['jpg', 'png'], 'mimes message');

        $this->assertPasses(
            ['file' => ''],
            ['file' => [$annotation->getRule()]],
            ['file.mimes' => 'mimes message']
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Mimes(['jpg', 'png'], 'mimes message');

        $this->assertPasses(
            ['file' => ''],
            ['file' => [$annotation->getRule()]],
            ['file.mimes' => 'mimes message']
        );

        $this->assertPasses(
            ['file' => ' '],
            ['file' => [$annotation->getRule()]],
            ['file.mimes' => 'mimes message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Mimes(['jpg', 'png'], 'mimes message');

        $this->assertPasses(
            [],
            ['file' => ['sometimes', $annotation->getRule()]],
            ['file.mimes' => 'mimes message']
        );
    }
}
