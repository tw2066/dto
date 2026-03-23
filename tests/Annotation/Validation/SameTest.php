<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Same;

/**
 * @internal
 * @coversNothing
 */
class SameTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Same('password', 'same message');

        $this->assertPasses(
            ['password' => 'secret', 'password_confirmation' => 'secret'],
            ['password_confirmation' => [$annotation->getRule()]],
            ['password_confirmation.same' => 'same message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Same('password', 'same message');

        $this->assertFailsWithMessage(
            ['password' => 'secret', 'password_confirmation' => 'different'],
            ['password_confirmation' => [$annotation->getRule()]],
            ['password_confirmation.same' => 'same message'],
            'password_confirmation',
            'same message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Same('field', 'same message');

        $this->assertPasses(
            ['field' => '', 'confirm' => ''],
            ['confirm' => [$annotation->getRule()]],
            ['confirm.same' => 'same message']
        );

        $this->assertPasses(
            ['field' => 0, 'confirm' => 0],
            ['confirm' => [$annotation->getRule()]],
            ['confirm.same' => 'same message']
        );

        $this->assertFailsWithMessage(
            ['field' => null, 'confirm' => 'value'],
            ['confirm' => [$annotation->getRule()]],
            ['confirm.same' => 'same message'],
            'confirm',
            'same message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Same('password', 'same message');

        $this->assertPasses(
            [],
            ['confirm' => ['sometimes', $annotation->getRule()]],
            ['confirm.same' => 'same message']
        );
    }
}
