<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\ExcludeWithout;

/**
 * @internal
 * @coversNothing
 */
class ExcludeWithoutTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new ExcludeWithout('a');

        $validator = $this->makeValidatorFactory()->make(['v' => 'any'], ['v' => [$annotation->getRule()]]);
        self::assertSame([], $validator->validate());
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new ExcludeWithout('a');

        $this->assertFailsWithMessage(
            ['a' => 1, 'v' => 'not-an-ip'],
            ['v' => [$annotation->getRule(), 'ip']],
            ['v.ip' => 'ip message'],
            'v',
            'ip message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new ExcludeWithout('a');

        $validator = $this->makeValidatorFactory()->make(['v' => null], ['v' => [$annotation->getRule()]]);
        self::assertSame([], $validator->validate());
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new ExcludeWithout('a');

        $validator = $this->makeValidatorFactory()->make([], ['v' => ['sometimes', $annotation->getRule()]]);
        self::assertSame([], $validator->validate());
    }
}
