<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Mimetypes;

/**
 * @internal
 * @coversNothing
 */
class MimetypesTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Mimetypes(['image/jpeg', 'image/png'], 'mimetypes message');

        $this->assertPasses(
            ['file' => ''],
            ['file' => [$annotation->getRule()]],
            ['file.mimetypes' => 'mimetypes message']
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Mimetypes(['image/jpeg', 'image/png'], 'mimetypes message');

        $this->assertPasses(
            ['file' => ''],
            ['file' => [$annotation->getRule()]],
            ['file.mimetypes' => 'mimetypes message']
        );

        $this->assertPasses(
            ['file' => ' '],
            ['file' => [$annotation->getRule()]],
            ['file.mimetypes' => 'mimetypes message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Mimetypes(['image/jpeg', 'image/png'], 'mimetypes message');

        $this->assertPasses(
            [],
            ['file' => ['sometimes', $annotation->getRule()]],
            ['file.mimetypes' => 'mimetypes message']
        );
    }
}
