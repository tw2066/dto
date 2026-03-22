<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\File;
use SplFileInfo;

/**
 * @internal
 * @coversNothing
 */
class FileTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $file = new SplFileInfo($this->createTempFile('.txt', 'x'));
        $annotation = new File('file message');

        $this->assertPasses(
            ['v' => $file],
            ['v' => [$annotation->getRule()]],
            ['v.file' => 'file message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new File('file message');

        $this->assertFailsWithMessage(
            ['v' => 'not-file'],
            ['v' => [$annotation->getRule()]],
            ['v.file' => 'file message'],
            'v',
            'file message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new File('file message');

        $this->assertPasses(
            ['v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.file' => 'file message']
        );

        $this->assertPasses(
            ['v' => null],
            ['v' => ['nullable', $annotation->getRule()]],
            ['v.file' => 'file message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new File('file message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.file' => 'file message']
        );

        $this->assertFailsWithMessage(
            ['v' => 'not-file'],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.file' => 'file message'],
            'v',
            'file message'
        );
    }
}

