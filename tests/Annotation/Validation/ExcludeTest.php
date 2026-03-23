<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Exclude;

/**
 * @internal
 * @coversNothing
 */
class ExcludeTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Exclude();

        $validator = $this->makeValidatorFactory()->make(['v' => 'any'], ['v' => [$annotation->getRule()]]);
        self::assertSame([], $validator->validate());
    }

    public function testExcludesEvenIfOtherRulesFail(): void
    {
        $annotation = new Exclude();

        $validator = $this->makeValidatorFactory()->make(['v' => 'not-an-ip'], ['v' => [$annotation->getRule(), 'ip']], ['v.ip' => 'ip message']);
        self::assertSame([], $validator->validate());
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Exclude();

        $validator = $this->makeValidatorFactory()->make(['v' => ''], ['v' => [$annotation->getRule()]]);
        self::assertSame([], $validator->validate());

        $validator = $this->makeValidatorFactory()->make(['v' => 0], ['v' => [$annotation->getRule()]]);
        self::assertSame([], $validator->validate());

        $validator = $this->makeValidatorFactory()->make(['v' => null], ['v' => [$annotation->getRule()]]);
        self::assertSame([], $validator->validate());

        $validator = $this->makeValidatorFactory()->make(['v' => []], ['v' => [$annotation->getRule()]]);
        self::assertSame([], $validator->validate());

        $validator = $this->makeValidatorFactory()->make(['v' => (object) ['a' => 1]], ['v' => [$annotation->getRule()]]);
        self::assertSame([], $validator->validate());
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Exclude();

        $validator = $this->makeValidatorFactory()->make([], ['v' => ['sometimes', $annotation->getRule()]]);
        self::assertSame([], $validator->validate());
    }
}
