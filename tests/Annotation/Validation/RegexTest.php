<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Regex;

/**
 * @internal
 * @coversNothing
 */
class RegexTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Regex('/^[0-9]+$/', 'regex message');

        $this->assertPasses(
            ['value' => '123'],
            ['value' => [$annotation->getRule()]],
            ['value.regex' => 'regex message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Regex('/^[0-9]+$/', 'regex message');

        $this->assertFailsWithMessage(
            ['value' => 'abc'],
            ['value' => [$annotation->getRule()]],
            ['value.regex' => 'regex message'],
            'value',
            'regex message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Regex('/^[0-9]+$/', 'regex message');

        $this->assertPasses(
            ['value' => ''],
            ['value' => [$annotation->getRule()]],
            ['value.regex' => 'regex message']
        );

        $this->assertFailsWithMessage(
            ['value' => null],
            ['value' => [$annotation->getRule()]],
            ['value.regex' => 'regex message'],
            'value',
            'regex message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Regex('/^[0-9]+$/', 'regex message');

        $this->assertPasses(
            [],
            ['value' => ['sometimes', $annotation->getRule()]],
            ['value.regex' => 'regex message']
        );

        $this->assertFailsWithMessage(
            ['value' => 'abc'],
            ['value' => ['sometimes', $annotation->getRule()]],
            ['value.regex' => 'regex message'],
            'value',
            'regex message'
        );
    }
}
