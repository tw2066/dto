<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\Translation\Translator;
use Hyperf\Validation\ValidationException;
use Hyperf\Validation\ValidatorFactory;
use PHPUnit\Framework\TestCase;

abstract class ValidationAnnotationTestCase extends TestCase
{
    protected array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        $this->tempFiles = [];
        parent::tearDown();
    }

    protected function makeValidatorFactory(): ValidatorFactory
    {
        $translator = new Translator(new FakeTranslatorLoader(), 'en');
        $translator->setFallback('en');
        return new ValidatorFactory($translator);
    }

    protected function createTempFile(string $suffix, string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'dto_');
        if (! is_string($file)) {
            self::fail('tempnam failed');
        }
        $newFile = $file . $suffix;
        rename($file, $newFile);
        file_put_contents($newFile, $contents);
        $this->tempFiles[] = $newFile;
        return $newFile;
    }

    protected function assertPasses(array $data, array $rules, array $messages = []): void
    {
        $validator = $this->makeValidatorFactory()->make($data, $rules, $messages);
        $validator->validate();
        self::assertSame(false, $validator->fails());
    }

    protected function assertFailsWithMessage(array $data, array $rules, array $messages, string $field, string $expectedMessage): void
    {
        $validator = $this->makeValidatorFactory()->make($data, $rules, $messages);
        try {
            $validator->validate();
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            self::assertSame(true, $exception->validator->fails());
            self::assertSame($expectedMessage, $exception->validator->errors()->first($field));
        }
    }
}
