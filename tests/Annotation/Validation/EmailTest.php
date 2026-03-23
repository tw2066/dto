<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Email;

/**
 * @internal
 * @coversNothing
 */
class EmailTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Email('email message');

        $this->assertPasses(
            ['email' => 'test@example.com'],
            ['email' => [$annotation->getRule()]],
            ['email.email' => 'email message']
        );

        $this->assertPasses(
            ['email' => 'user.name@domain.co.uk'],
            ['email' => [$annotation->getRule()]],
            ['email.email' => 'email message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Email('email message');

        $this->assertFailsWithMessage(
            ['email' => 'invalid-email'],
            ['email' => [$annotation->getRule()]],
            ['email.email' => 'email message'],
            'email',
            'email message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Email('email message');

        $this->assertPasses(
            ['email' => ''],
            ['email' => [$annotation->getRule()]],
            ['email.email' => 'email message']
        );

        $this->assertFailsWithMessage(
            ['email' => null],
            ['email' => [$annotation->getRule()]],
            ['email.email' => 'email message'],
            'email',
            'email message'
        );

        $this->assertFailsWithMessage(
            ['email' => 0],
            ['email' => [$annotation->getRule()]],
            ['email.email' => 'email message'],
            'email',
            'email message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Email('email message');

        $this->assertPasses(
            [],
            ['email' => ['sometimes', $annotation->getRule()]],
            ['email.email' => 'email message']
        );

        $this->assertFailsWithMessage(
            ['email' => 'invalid-email'],
            ['email' => ['sometimes', $annotation->getRule()]],
            ['email.email' => 'email message'],
            'email',
            'email message'
        );
    }
}
