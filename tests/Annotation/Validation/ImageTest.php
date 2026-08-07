<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Image;

/**
 * @internal
 * @coversNothing
 */
class ImageTest extends ValidationAnnotationTestCase
{
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    public function testPasses(): void
    {
        $annotation = new Image('image message');
        $file = $this->createTempFile('.png', base64_decode(self::PNG_1X1));

        $this->assertPasses(
            ['file' => new \SplFileInfo($file)],
            ['file' => [$annotation->getRule()]],
            ['file.image' => 'image message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Image('image message');
        $file = $this->createTempFile('.txt', 'plain text');

        $this->assertFailsWithMessage(
            ['file' => new \SplFileInfo($file)],
            ['file' => [$annotation->getRule()]],
            ['file.image' => 'image message'],
            'file',
            'image message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Image('image message');

        // 空字符串触发 presentOrRuleIsImplicit 跳过语义，非隐式规则不执行
        $this->assertPasses(
            ['file' => ''],
            ['file' => [$annotation->getRule()]],
            ['file.image' => 'image message']
        );

        // nullable + null 通过
        $this->assertPasses(
            ['file' => null],
            ['file' => ['nullable', $annotation->getRule()]],
            ['file.image' => 'image message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Image('image message');

        $this->assertPasses(
            [],
            ['file' => ['sometimes', $annotation->getRule()]],
            ['file.image' => 'image message']
        );
    }
}
