<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Json;

/**
 * @internal
 * @coversNothing
 */
class JsonTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Json('json message');

        $this->assertPasses(
            ['data' => '{"name": "John"}'],
            ['data' => [$annotation->getRule()]],
            ['data.json' => 'json message']
        );

        $this->assertPasses(
            ['data' => '["a", "b", "c"]'],
            ['data' => [$annotation->getRule()]],
            ['data.json' => 'json message']
        );

        $this->assertPasses(
            ['data' => '123'],
            ['data' => [$annotation->getRule()]],
            ['data.json' => 'json message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Json('json message');

        $this->assertFailsWithMessage(
            ['data' => 'invalid json'],
            ['data' => [$annotation->getRule()]],
            ['data.json' => 'json message'],
            'data',
            'json message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Json('json message');

        $this->assertPasses(
            ['data' => ''],
            ['data' => [$annotation->getRule()]],
            ['data.json' => 'json message']
        );

        $this->assertFailsWithMessage(
            ['data' => null],
            ['data' => [$annotation->getRule()]],
            ['data.json' => 'json message'],
            'data',
            'json message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Json('json message');

        $this->assertPasses(
            [],
            ['data' => ['sometimes', $annotation->getRule()]],
            ['data.json' => 'json message']
        );

        $this->assertFailsWithMessage(
            ['data' => 'invalid json'],
            ['data' => ['sometimes', $annotation->getRule()]],
            ['data.json' => 'json message'],
            'data',
            'json message'
        );
    }
}
