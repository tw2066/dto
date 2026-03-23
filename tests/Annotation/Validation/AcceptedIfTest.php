<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\AcceptedIf;

/**
 * @internal
 * @coversNothing
 */
class AcceptedIfTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new AcceptedIf('status', 1, 'accepted_if message');

        $this->assertPasses(
            ['status' => 1, 'agree' => 'yes'],
            ['agree' => [$annotation->getRule()]],
            ['agree.accepted_if' => 'accepted_if message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new AcceptedIf('status', 1, 'accepted_if message');

        $this->assertFailsWithMessage(
            ['status' => 1, 'agree' => 'no'],
            ['agree' => [$annotation->getRule()]],
            ['agree.accepted_if' => 'accepted_if message'],
            'agree',
            'accepted_if message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new AcceptedIf('status', 1, 'accepted_if message');

        $this->assertFailsWithMessage(
            ['status' => 1, 'agree' => ''],
            ['agree' => [$annotation->getRule()]],
            ['agree.accepted_if' => 'accepted_if message'],
            'agree',
            'accepted_if message'
        );

        $this->assertFailsWithMessage(
            ['status' => 1, 'agree' => 0],
            ['agree' => [$annotation->getRule()]],
            ['agree.accepted_if' => 'accepted_if message'],
            'agree',
            'accepted_if message'
        );

        $this->assertPasses(
            ['status' => 0, 'agree' => null],
            ['agree' => [$annotation->getRule()]],
            ['agree.accepted_if' => 'accepted_if message']
        );

        $this->assertFailsWithMessage(
            ['status' => 1, 'agree' => []],
            ['agree' => [$annotation->getRule()]],
            ['agree.accepted_if' => 'accepted_if message'],
            'agree',
            'accepted_if message'
        );

        $this->assertFailsWithMessage(
            ['status' => 1, 'agree' => (object) ['a' => 1]],
            ['agree' => [$annotation->getRule()]],
            ['agree.accepted_if' => 'accepted_if message'],
            'agree',
            'accepted_if message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new AcceptedIf('status', 1, 'accepted_if message');

        $this->assertPasses(
            ['status' => 1],
            ['agree' => ['sometimes', $annotation->getRule()]],
            ['agree.accepted_if' => 'accepted_if message']
        );

        $this->assertFailsWithMessage(
            ['status' => 1, 'agree' => 'no'],
            ['agree' => ['sometimes', $annotation->getRule()]],
            ['agree.accepted_if' => 'accepted_if message'],
            'agree',
            'accepted_if message'
        );
    }
}
