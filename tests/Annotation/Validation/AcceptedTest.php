<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Accepted;

/**
 * @internal
 * @coversNothing
 */
class AcceptedTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Accepted('accepted message');

        $this->assertPasses(
            ['agree' => 'yes'],
            ['agree' => [$annotation->getRule()]],
            ['agree.accepted' => 'accepted message']
        );

        $this->assertPasses(
            ['agree' => 'on'],
            ['agree' => [$annotation->getRule()]],
            ['agree.accepted' => 'accepted message']
        );

        $this->assertPasses(
            ['agree' => '1'],
            ['agree' => [$annotation->getRule()]],
            ['agree.accepted' => 'accepted message']
        );

        $this->assertPasses(
            ['agree' => 1],
            ['agree' => [$annotation->getRule()]],
            ['agree.accepted' => 'accepted message']
        );

        $this->assertPasses(
            ['agree' => true],
            ['agree' => [$annotation->getRule()]],
            ['agree.accepted' => 'accepted message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Accepted('accepted message');

        $this->assertFailsWithMessage(
            ['agree' => 'no'],
            ['agree' => [$annotation->getRule()]],
            ['agree.accepted' => 'accepted message'],
            'agree',
            'accepted message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Accepted('accepted message');

        $this->assertFailsWithMessage(
            ['agree' => ''],
            ['agree' => [$annotation->getRule()]],
            ['agree.accepted' => 'accepted message'],
            'agree',
            'accepted message'
        );

        $this->assertFailsWithMessage(
            ['agree' => 0],
            ['agree' => [$annotation->getRule()]],
            ['agree.accepted' => 'accepted message'],
            'agree',
            'accepted message'
        );

        $this->assertFailsWithMessage(
            ['agree' => null],
            ['agree' => [$annotation->getRule()]],
            ['agree.accepted' => 'accepted message'],
            'agree',
            'accepted message'
        );

        $this->assertFailsWithMessage(
            ['agree' => []],
            ['agree' => [$annotation->getRule()]],
            ['agree.accepted' => 'accepted message'],
            'agree',
            'accepted message'
        );

        $this->assertFailsWithMessage(
            ['agree' => (object) ['a' => 1]],
            ['agree' => [$annotation->getRule()]],
            ['agree.accepted' => 'accepted message'],
            'agree',
            'accepted message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Accepted('accepted message');

        $this->assertPasses(
            [],
            ['agree' => ['sometimes', $annotation->getRule()]],
            ['agree.accepted' => 'accepted message']
        );

        $this->assertFailsWithMessage(
            ['agree' => 'no'],
            ['agree' => ['sometimes', $annotation->getRule()]],
            ['agree.accepted' => 'accepted message'],
            'agree',
            'accepted message'
        );
    }
}
