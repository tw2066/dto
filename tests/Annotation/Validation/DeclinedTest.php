<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Declined;

/**
 * @internal
 * @coversNothing
 */
class DeclinedTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Declined('declined message');

        $this->assertPasses(
            ['agree' => 'no'],
            ['agree' => [$annotation->getRule()]],
            ['agree.declined' => 'declined message']
        );

        $this->assertPasses(
            ['agree' => 'off'],
            ['agree' => [$annotation->getRule()]],
            ['agree.declined' => 'declined message']
        );

        $this->assertPasses(
            ['agree' => '0'],
            ['agree' => [$annotation->getRule()]],
            ['agree.declined' => 'declined message']
        );

        $this->assertPasses(
            ['agree' => 0],
            ['agree' => [$annotation->getRule()]],
            ['agree.declined' => 'declined message']
        );

        $this->assertPasses(
            ['agree' => false],
            ['agree' => [$annotation->getRule()]],
            ['agree.declined' => 'declined message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Declined('declined message');

        $this->assertFailsWithMessage(
            ['agree' => 'yes'],
            ['agree' => [$annotation->getRule()]],
            ['agree.declined' => 'declined message'],
            'agree',
            'declined message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Declined('declined message');

        $this->assertPasses(
            ['agree' => 0],
            ['agree' => [$annotation->getRule()]],
            ['agree.declined' => 'declined message']
        );

        $this->assertFailsWithMessage(
            ['agree' => 1],
            ['agree' => [$annotation->getRule()]],
            ['agree.declined' => 'declined message'],
            'agree',
            'declined message'
        );

        $this->assertFailsWithMessage(
            ['agree' => null],
            ['agree' => [$annotation->getRule()]],
            ['agree.declined' => 'declined message'],
            'agree',
            'declined message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Declined('declined message');

        $this->assertPasses(
            [],
            ['agree' => ['sometimes', $annotation->getRule()]],
            ['agree.declined' => 'declined message']
        );

        $this->assertFailsWithMessage(
            ['agree' => 'yes'],
            ['agree' => ['sometimes', $annotation->getRule()]],
            ['agree.declined' => 'declined message'],
            'agree',
            'declined message'
        );
    }
}
