<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Confirmed;

/**
 * @internal
 * @coversNothing
 */
class ConfirmedTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Confirmed('confirmed message');

        $this->assertPasses(
            ['password' => 'abc', 'password_confirmation' => 'abc'],
            ['password' => [$annotation->getRule()]],
            ['password.confirmed' => 'confirmed message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Confirmed('confirmed message');

        $this->assertFailsWithMessage(
            ['password' => 'abc', 'password_confirmation' => 'abcd'],
            ['password' => [$annotation->getRule()]],
            ['password.confirmed' => 'confirmed message'],
            'password',
            'confirmed message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Confirmed('confirmed message');

        $this->assertPasses(
            ['password' => '', 'password_confirmation' => ''],
            ['password' => [$annotation->getRule()]],
            ['password.confirmed' => 'confirmed message']
        );

        $this->assertPasses(
            ['password' => null, 'password_confirmation' => null],
            ['password' => [$annotation->getRule()]],
            ['password.confirmed' => 'confirmed message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Confirmed('confirmed message');

        $this->assertPasses(
            [],
            ['password' => ['sometimes', $annotation->getRule()]],
            ['password.confirmed' => 'confirmed message']
        );

        $this->assertFailsWithMessage(
            ['password' => 'abc', 'password_confirmation' => 'abcd'],
            ['password' => ['sometimes', $annotation->getRule()]],
            ['password.confirmed' => 'confirmed message'],
            'password',
            'confirmed message'
        );
    }
}
