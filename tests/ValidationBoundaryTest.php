<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\Annotation\Validation\Required;
use Hyperf\DTO\Annotation\Validation\Nullable;
use Hyperf\DTO\Annotation\Validation\Sometimes;
use Hyperf\DTO\Annotation\Validation\Bail;
use Hyperf\DTO\Annotation\Validation\Filled;
use Hyperf\DTO\Annotation\Validation\Present;
use Hyperf\DTO\Annotation\Validation\Missing;
use Hyperf\DTO\Annotation\Validation\Exclude;
use PHPUnit\Framework\TestCase;

class ValidationBoundaryTest extends TestCase
{
    public function testRequiredBoundaryConditions(): void
    {
        $attr = new Required();
        $this->assertSame('required', $attr->getRule());
        $this->assertSame('', $attr->messages);
    }

    public function testRequiredWithEmptyMessage(): void
    {
        $attr = new Required('');
        $this->assertSame('required', $attr->getRule());
        $this->assertSame('', $attr->messages);
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

    public function testPresentAttribute(): void
    {
        $attr = new Present();
        $this->assertSame('present', $attr->getRule());

        $attr2 = new Present('Must be present');
        $this->assertSame('present', $attr2->getRule());
        $this->assertSame('Must be present', $attr2->messages);
    }

    public function testMissingAttribute(): void
    {
        $attr = new Missing();
        $this->assertSame('missing', $attr->getRule());

        $attr2 = new Missing('Must be missing');
        $this->assertSame('missing', $attr2->getRule());
        $this->assertSame('Must be missing', $attr2->messages);
    }

    public function testExcludeAttribute(): void
    {
        $attr = new Exclude();
        $this->assertSame('exclude', $attr->getRule());

        $attr2 = new Exclude('Must be excluded');
        $this->assertSame('exclude', $attr2->getRule());
        $this->assertSame('Must be excluded', $attr2->messages);
    }
}