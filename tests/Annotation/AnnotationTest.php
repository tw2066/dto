<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation;

use Hyperf\DTO\Annotation\ArrayType;
use Hyperf\DTO\Annotation\Contracts\RequestBody;
use Hyperf\DTO\Annotation\Contracts\RequestFormData;
use Hyperf\DTO\Annotation\Contracts\RequestHeader;
use Hyperf\DTO\Annotation\Contracts\RequestQuery;
use Hyperf\DTO\Annotation\Contracts\Valid;
use Hyperf\DTO\Annotation\Dto;
use Hyperf\DTO\Annotation\JSONField;
use Hyperf\DTO\Annotation\Validation\Email;
use Hyperf\DTO\Annotation\Validation\Required;
use Hyperf\DTO\Annotation\Validation\Validation;
use Hyperf\DTO\Type\Convert;
use Hyperf\DTO\Type\PhpType;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class AnnotationTest extends TestCase
{
    public function testRequestBodyAttribute(): void
    {
        $attr = new RequestBody();
        $this->assertInstanceOf(RequestBody::class, $attr);
    }

    public function testRequestQueryAttribute(): void
    {
        $attr = new RequestQuery();
        $this->assertInstanceOf(RequestQuery::class, $attr);
    }

    public function testRequestHeaderAttribute(): void
    {
        $attr = new RequestHeader();
        $this->assertInstanceOf(RequestHeader::class, $attr);
    }

    public function testRequestFormDataAttribute(): void
    {
        $attr = new RequestFormData();
        $this->assertInstanceOf(RequestFormData::class, $attr);
    }

    public function testValidAttribute(): void
    {
        $attr = new Valid();
        $this->assertInstanceOf(Valid::class, $attr);
    }

    public function testJsonFieldAttribute(): void
    {
        $attr = new JSONField('custom_field');
        $this->assertSame('custom_field', $attr->name);
    }

    public function testArrayTypeAttribute(): void
    {
        $attr = new ArrayType(PhpType::STRING);
        $this->assertSame('string', $attr->value);
    }

    public function testDtoAttribute(): void
    {
        $attr = new Dto();
        $this->assertNull($attr->responseConvert);
    }

    public function testDtoAttributeWithResponseConvert(): void
    {
        $attr = new Dto(Convert::CAMEL);
        $this->assertSame(Convert::CAMEL, $attr->responseConvert);
    }

    public function testValidationAttribute(): void
    {
        $attr = new Validation('required|string|max:255', 'The field is required');
        $this->assertSame('required|string|max:255', $attr->getRule());
        $this->assertSame('The field is required', $attr->messages);
    }

    public function testValidationAttributeWithCustomKey(): void
    {
        $attr = new Validation('integer', '', 'items.*');
        $this->assertSame('integer', $attr->getRule());
        $this->assertSame('items.*', $attr->getCustomKey());
    }

    public function testRequiredAttribute(): void
    {
        $attr = new Required('Field is required');
        $this->assertSame('required', $attr->getRule());
        $this->assertSame('Field is required', $attr->messages);
    }

    public function testEmailAttribute(): void
    {
        $attr = new Email('Invalid email');
        $this->assertSame('email', $attr->getRule());
        $this->assertSame('Invalid email', $attr->messages);
    }
}
