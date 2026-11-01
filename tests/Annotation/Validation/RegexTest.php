<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Regex;
use Hyperf\DTO\Annotation\Validation\Validation;
use Hyperf\DTO\Scan\ValidationManager;

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

    public function testPipeAlternationThroughValidationManager(): void
    {
        $annotation = new Regex('/^(foo|bar)+$/', 'regex message');
        $annotation->collectProperty(RegexPipeDto::class, 'value');
        $manager = new ValidationManager();
        $manager->generateValidation(RegexPipeDto::class, 'value');
        $data = $manager->getData(RegexPipeDto::class);

        $this->assertSame(['value' => ['regex:/^(foo|bar)+$/']], $data['rule']);
        $this->assertSame(['value.regex' => 'regex message'], $data['messages']);
        $this->assertPasses(['value' => 'foofoobar'], $data['rule'], $data['messages']);
        $this->assertFailsWithMessage(['value' => 'baz'], $data['rule'], $data['messages'], 'value', 'regex message');
    }

    public function testPipeAlternationInArrayRules(): void
    {
        $annotation = new Validation(['required', 'regex:/^(foo|bar)+$/']);
        $annotation->collectProperty(RegexArrayPipeDto::class, 'value');
        $manager = new ValidationManager();
        $manager->generateValidation(RegexArrayPipeDto::class, 'value');
        $data = $manager->getData(RegexArrayPipeDto::class);

        $this->assertSame(['value' => ['required', 'regex:/^(foo|bar)+$/']], $data['rule']);
        $this->assertPasses(['value' => 'foobar'], $data['rule']);
    }
}

class RegexPipeDto
{
    public string $value = '';
}

class RegexArrayPipeDto
{
    public string $value = '';
}
