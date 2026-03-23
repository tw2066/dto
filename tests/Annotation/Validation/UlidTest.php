<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Ulid;

/**
 * @internal
 * @coversNothing
 */
class UlidTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Ulid('ulid message');

        $this->assertPasses(
            ['id' => '01H886KQZJQ6ZQZQZQZQZQZQZQ'],
            ['id' => [$annotation->getRule()]],
            ['id.ulid' => 'ulid message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Ulid('ulid message');

        $this->assertFailsWithMessage(
            ['id' => 'not-a-ulid'],
            ['id' => [$annotation->getRule()]],
            ['id.ulid' => 'ulid message'],
            'id',
            'ulid message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Ulid('ulid message');

        $this->assertPasses(
            [],
            ['id' => ['sometimes', $annotation->getRule()]],
            ['id.ulid' => 'ulid message']
        );
    }
}
