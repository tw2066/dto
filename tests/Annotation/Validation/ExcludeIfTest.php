<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\ExcludeIf;

/**
 * @internal
 * @coversNothing
 */
class ExcludeIfTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new ExcludeIf('status', 1);

        $validator = $this->makeValidatorFactory()->make(['status' => 1, 'v' => 'any'], ['v' => [$annotation->getRule()]]);
        self::assertSame([], $validator->validate());
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new ExcludeIf('status', 1);

        $this->assertFailsWithMessage(
            ['status' => 0, 'v' => 'not-an-ip'],
            ['v' => [$annotation->getRule(), 'ip']],
            ['v.ip' => 'ip message'],
            'v',
            'ip message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new ExcludeIf('status', 1);

        $validator = $this->makeValidatorFactory()->make(['status' => 1, 'v' => null], ['v' => [$annotation->getRule()]]);
        self::assertSame([], $validator->validate());
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new ExcludeIf('status', 1);

        $validator = $this->makeValidatorFactory()->make(['status' => 1], ['v' => ['sometimes', $annotation->getRule()]]);
        self::assertSame([], $validator->validate());
    }
}

