<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\InArray;

/**
 * @internal
 * @coversNothing
 */
class InArrayTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new InArray('valid_options.*', 'in_array message');

        $this->assertPasses(
            ['valid_options' => ['a', 'b', 'c'], 'choice' => 'a'],
            ['choice' => [$annotation->getRule()]],
            ['choice.in_array' => 'in_array message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new InArray('valid_options.*', 'in_array message');

        $this->assertFailsWithMessage(
            ['valid_options' => ['a', 'b', 'c'], 'choice' => 'd'],
            ['choice' => [$annotation->getRule()]],
            ['choice.in_array' => 'in_array message'],
            'choice',
            'in_array message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new InArray('valid_options.*', 'in_array message');

        // 空字符串触发跳过语义，非隐式规则不执行
        $this->assertPasses(
            ['valid_options' => ['a'], 'choice' => ''],
            ['choice' => [$annotation->getRule()]],
            ['choice.in_array' => 'in_array message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new InArray('valid_options', 'in_array message');

        $this->assertPasses(
            [],
            ['choice' => ['sometimes', $annotation->getRule()]],
            ['choice.in_array' => 'in_array message']
        );
    }
}
