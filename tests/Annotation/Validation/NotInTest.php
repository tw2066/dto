<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\NotIn;

/**
 * @internal
 * @coversNothing
 */
class NotInTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new NotIn(['a', 'b', 'c'], 'not_in message');

        $this->assertPasses(
            ['choice' => 'd'],
            ['choice' => [$annotation->getRule()]],
            ['choice.not_in' => 'not_in message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new NotIn(['a', 'b', 'c'], 'not_in message');

        $this->assertFailsWithMessage(
            ['choice' => 'a'],
            ['choice' => [$annotation->getRule()]],
            ['choice.not_in' => 'not_in message'],
            'choice',
            'not_in message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new NotIn(['a', 'b', 'c'], 'not_in message');

        $this->assertPasses(
            ['choice' => ''],
            ['choice' => [$annotation->getRule()]],
            ['choice.not_in' => 'not_in message']
        );

        $this->assertFailsWithMessage(
            ['choice' => 'a'],
            ['choice' => [$annotation->getRule()]],
            ['choice.not_in' => 'not_in message'],
            'choice',
            'not_in message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new NotIn(['a', 'b', 'c'], 'not_in message');

        $this->assertPasses(
            [],
            ['choice' => ['sometimes', $annotation->getRule()]],
            ['choice.not_in' => 'not_in message']
        );

        $this->assertFailsWithMessage(
            ['choice' => 'a'],
            ['choice' => ['sometimes', $annotation->getRule()]],
            ['choice.not_in' => 'not_in message'],
            'choice',
            'not_in message'
        );
    }
}
