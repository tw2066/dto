<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\NotRegex;
use Hyperf\DTO\Scan\ValidationManager;

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

    public function testPipeAlternationThroughValidationManager(): void
    {
        $annotation = new NotRegex('/^(foo|bar)+$/', 'not_regex message');
        $annotation->collectProperty(NotRegexPipeDto::class, 'value');
        $manager = new ValidationManager();
        $manager->generateValidation(NotRegexPipeDto::class, 'value');
        $data = $manager->getData(NotRegexPipeDto::class);

        $this->assertSame(['value' => ['not_regex:/^(foo|bar)+$/']], $data['rule']);
        $this->assertSame(['value.not_regex' => 'not_regex message'], $data['messages']);
        $this->assertPasses(['value' => 'baz'], $data['rule'], $data['messages']);
        $this->assertFailsWithMessage(['value' => 'foobar'], $data['rule'], $data['messages'], 'value', 'not_regex message');
    }
}

class NotRegexPipeDto
{
    public string $value = '';
}
