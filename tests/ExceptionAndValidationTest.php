<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\Annotation\Validation\Required;
use Hyperf\DTO\Annotation\Validation\Nullable;
use Hyperf\DTO\Annotation\Validation\Sometimes;
use Hyperf\DTO\Annotation\Validation\Bail;
use Hyperf\DTO\Annotation\Validation\Filled;
use Hyperf\DTO\Exception\DtoException;
use PHPUnit\Framework\TestCase;

class ExceptionAndValidationTest extends TestCase
{
    public function testDtoExceptionExtendsRuntimeException(): void
    {
        $exception = new DtoException('Test message');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('Test message', $exception->getMessage());
    }

    public function testDtoExceptionWithCode(): void
    {
        $exception = new DtoException('Test message', 500);

        $this->assertSame(500, $exception->getCode());
    }

    public function testRequiredAttribute(): void
    {
        $attr = new Required();
        $this->assertSame('required', $attr->getRule());
        $this->assertSame('', $attr->messages);
    }

    public function testRequiredAttributeWithMessage(): void
    {
        $attr = new Required('Field is required');
        $this->assertSame('required', $attr->getRule());
        $this->assertSame('Field is required', $attr->messages);
    }

    public function testNullableAttribute(): void
    {
        $attr = new Nullable();
        $this->assertSame('nullable', $attr->getRule());

        $attr2 = new Nullable('Can be null');
        $this->assertSame('nullable', $attr2->getRule());
        $this->assertSame('Can be null', $attr2->messages);
    }

    public function testSometimesAttribute(): void
    {
        $attr = new Sometimes();
        $this->assertSame('sometimes', $attr->getRule());

        $attr2 = new Sometimes('Sometimes required');
        $this->assertSame('sometimes', $attr2->getRule());
        $this->assertSame('Sometimes required', $attr2->messages);
    }

    public function testBailAttribute(): void
    {
        $attr = new Bail();
        $this->assertSame('bail', $attr->getRule());

        $attr2 = new Bail('Stop on first error');
        $this->assertSame('bail', $attr2->getRule());
        $this->assertSame('Stop on first error', $attr2->messages);
    }

    public function testFilledAttribute(): void
    {
        $attr = new Filled();
        $this->assertSame('filled', $attr->getRule());

        $attr2 = new Filled('Must be filled');
        $this->assertSame('filled', $attr2->getRule());
        $this->assertSame('Must be filled', $attr2->messages);
    }
}