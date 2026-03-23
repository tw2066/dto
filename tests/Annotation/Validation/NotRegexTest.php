<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\NotRegex;

/**
 * @internal
 * @coversNothing
 */
class NotRegexTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new NotRegex('/^[0-9]+$/', 'not_regex message');

        $this->assertPasses(
            ['value' => 'abc'],
            ['value' => [$annotation->getRule()]],
            ['value.not_regex' => 'not_regex message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new NotRegex('/^[0-9]+$/', 'not_regex message');

        $this->assertFailsWithMessage(
            ['value' => '123'],
            ['value' => [$annotation->getRule()]],
            ['value.not_regex' => 'not_regex message'],
            'value',
            'not_regex message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new NotRegex('/^[0-9]+$/', 'not_regex message');

        $this->assertPasses(
            ['value' => ''],
            ['value' => [$annotation->getRule()]],
            ['value.not_regex' => 'not_regex message']
        );

        $this->assertFailsWithMessage(
            ['value' => null],
            ['value' => [$annotation->getRule()]],
            ['value.not_regex' => 'not_regex message'],
            'value',
            'not_regex message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new NotRegex('/^[0-9]+$/', 'not_regex message');

        $this->assertPasses(
            [],
            ['value' => ['sometimes', $annotation->getRule()]],
            ['value.not_regex' => 'not_regex message']
        );

        $this->assertFailsWithMessage(
            ['value' => '123'],
            ['value' => ['sometimes', $annotation->getRule()]],
            ['value.not_regex' => 'not_regex message'],
            'value',
            'not_regex message'
        );
    }
}
