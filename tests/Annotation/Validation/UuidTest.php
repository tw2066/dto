<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Uuid;

/**
 * @internal
 * @coversNothing
 */
class UuidTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Uuid('uuid message');

        $this->assertPasses(
            ['id' => '550e8400-e29b-41d4-a716-446655440000'],
            ['id' => [$annotation->getRule()]],
            ['id.uuid' => 'uuid message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Uuid('uuid message');

        $this->assertFailsWithMessage(
            ['id' => 'not-a-uuid'],
            ['id' => [$annotation->getRule()]],
            ['id.uuid' => 'uuid message'],
            'id',
            'uuid message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Uuid('uuid message');

        $this->assertPasses(
            [],
            ['id' => ['sometimes', $annotation->getRule()]],
            ['id.uuid' => 'uuid message']
        );
    }
}
