<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Extensions;
use SplFileInfo;

/**
 * @internal
 * @coversNothing
 */
class ExtensionsTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $file = new SplFileInfo($this->createTempFile('.jpg', 'x'));
        $annotation = new Extensions(['jpg'], 'extensions message');

        $this->assertPasses(
            ['v' => $file],
            ['v' => [$annotation->getRule()]],
            ['v.extensions' => 'extensions message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $file = new SplFileInfo($this->createTempFile('.png', 'x'));
        $annotation = new Extensions(['jpg'], 'extensions message');

        $this->assertFailsWithMessage(
            ['v' => $file],
            ['v' => [$annotation->getRule()]],
            ['v.extensions' => 'extensions message'],
            'v',
            'extensions message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Extensions(['jpg'], 'extensions message');

        $this->assertPasses(
            ['v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.extensions' => 'extensions message']
        );

        $this->assertFailsWithMessage(
            ['v' => 0],
            ['v' => [$annotation->getRule()]],
            ['v.extensions' => 'extensions message'],
            'v',
            'extensions message'
        );

        $this->assertPasses(
            ['v' => null],
            ['v' => ['nullable', $annotation->getRule()]],
            ['v.extensions' => 'extensions message']
        );

        $this->assertFailsWithMessage(
            ['v' => []],
            ['v' => [$annotation->getRule()]],
            ['v.extensions' => 'extensions message'],
            'v',
            'extensions message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $file = new SplFileInfo($this->createTempFile('.png', 'x'));
        $annotation = new Extensions(['jpg'], 'extensions message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.extensions' => 'extensions message']
        );

        $this->assertFailsWithMessage(
            ['v' => $file],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.extensions' => 'extensions message'],
            'v',
            'extensions message'
        );
    }
}
