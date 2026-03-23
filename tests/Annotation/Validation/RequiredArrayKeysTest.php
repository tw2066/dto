<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\RequiredArrayKeys;

/**
 * @internal
 * @coversNothing
 */
class RequiredArrayKeysTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new RequiredArrayKeys(['key1', 'key2'], 'required array keys message');

        $this->assertPasses(
            ['data' => ['key1' => 'value1', 'key2' => 'value2']],
            ['data' => [$annotation->getRule()]],
            ['data.required_array_keys' => 'required array keys message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new RequiredArrayKeys(['key1', 'key2'], 'required array keys message');

        $this->assertFailsWithMessage(
            ['data' => ['key1' => 'value1']],
            ['data' => [$annotation->getRule()]],
            ['data.required_array_keys' => 'required array keys message'],
            'data',
            'required array keys message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new RequiredArrayKeys(['key'], 'required array keys message');

        $this->assertPasses(
            ['data' => ['key' => 'value']],
            ['data' => [$annotation->getRule()]],
            ['data.required_array_keys' => 'required array keys message']
        );

        $this->assertFailsWithMessage(
            ['data' => []],
            ['data' => [$annotation->getRule()]],
            ['data.required_array_keys' => 'required array keys message'],
            'data',
            'required array keys message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new RequiredArrayKeys(['key1', 'key2'], 'required array keys message');

        $this->assertPasses(
            [],
            ['data' => ['sometimes', $annotation->getRule()]],
            ['data.required_array_keys' => 'required array keys message']
        );
    }
}
