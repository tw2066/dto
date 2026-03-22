<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\ExcludeWith;

/**
 * @internal
 * @coversNothing
 */
class ExcludeWithTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new ExcludeWith('a');

        $validator = $this->makeValidatorFactory()->make(['a' => 1, 'v' => 'any'], ['v' => [$annotation->getRule()]]);
        self::assertSame([], $validator->validate());
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new ExcludeWith('a');

        $this->assertFailsWithMessage(
            ['v' => 'not-an-ip'],
            ['v' => [$annotation->getRule(), 'ip']],
            ['v.ip' => 'ip message'],
            'v',
            'ip message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new ExcludeWith('a');

        $validator = $this->makeValidatorFactory()->make(['a' => 1, 'v' => null], ['v' => [$annotation->getRule()]]);
        self::assertSame([], $validator->validate());
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new ExcludeWith('a');

        $validator = $this->makeValidatorFactory()->make([], ['v' => ['sometimes', $annotation->getRule()]]);
        self::assertSame([], $validator->validate());
    }
}

