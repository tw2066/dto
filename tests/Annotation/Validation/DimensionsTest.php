<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Dimensions;

/**
 * @internal
 * @coversNothing
 */
class DimensionsTest extends ValidationAnnotationTestCase
{
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    public function testPasses(): void
    {
        $annotation = new Dimensions(['min_width=1', 'min_height=1', 'max_width=100', 'ratio=1'], 'dimensions message');
        $file = $this->createTempFile('.png', base64_decode(self::PNG_1X1));

        $this->assertPasses(
            ['file' => new FakeFile($file, 'image/png')],
            ['file' => [$annotation->getRule()]],
            ['file.dimensions' => 'dimensions message']
        );
    }

    public function testFailsWithMessage(): void
    {
        // 1x1 图片不满足 min_width=100
        $annotation = new Dimensions(['min_width=100'], 'dimensions message');
        $file = $this->createTempFile('.png', base64_decode(self::PNG_1X1));

        $this->assertFailsWithMessage(
            ['file' => new FakeFile($file, 'image/png')],
            ['file' => [$annotation->getRule()]],
            ['file.dimensions' => 'dimensions message'],
            'file',
            'dimensions message'
        );
    }

    public function testFailsWhenImageSizeUnreadable(): void
    {
        // 内容不是合法图片: getimagesize 返回 false
        $annotation = new Dimensions(['min_width=1'], 'dimensions message');
        $file = $this->createTempFile('.png', 'not a real png');

        $this->assertFailsWithMessage(
            ['file' => new FakeFile($file, 'image/png')],
            ['file' => [$annotation->getRule()]],
            ['file.dimensions' => 'dimensions message'],
            'file',
            'dimensions message'
        );
    }

    public function testSvgSkipsDimensionCheck(): void
    {
        // svg mime 直通，不检查尺寸（也不读文件内容）
        $annotation = new Dimensions(['min_width=100'], 'dimensions message');
        $file = $this->createTempFile('.svg', '<svg></svg>');

        $this->assertPasses(
            ['file' => new FakeFile($file, 'image/svg+xml')],
            ['file' => [$annotation->getRule()]],
            ['file.dimensions' => 'dimensions message']
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Dimensions(['min_width=1'], 'dimensions message');

        $this->assertPasses(
            ['file' => ''],
            ['file' => [$annotation->getRule()]],
            ['file.dimensions' => 'dimensions message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Dimensions(['min_width=1'], 'dimensions message');

        $this->assertPasses(
            [],
            ['file' => ['sometimes', $annotation->getRule()]],
            ['file.dimensions' => 'dimensions message']
        );
    }
}
