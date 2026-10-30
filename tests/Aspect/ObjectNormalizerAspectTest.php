<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Aspect;

use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\DTO\Aspect\ObjectNormalizerAspect;
use Hyperf\DTO\Mapper;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class ObjectNormalizerAspectTest extends TestCase
{
    public function testMapsDataToDto(): void
    {
        $joinPoint = $this->makeJoinPoint(['name' => 'alice', 'age' => 30], ObjectNormalizerProbeDto::class);
        $result = (new ObjectNormalizerAspect())->process($joinPoint);

        $this->assertInstanceOf(ObjectNormalizerProbeDto::class, $result);
        $this->assertSame('alice', $result->name);
        $this->assertSame(30, $result->age);
    }

    public function testIgnoresUnknownKeys(): void
    {
        $joinPoint = $this->makeJoinPoint(['name' => 'alice', 'unknown' => 'ignored'], ObjectNormalizerProbeDto::class);
        $result = (new ObjectNormalizerAspect())->process($joinPoint);

        $this->assertSame('alice', $result->name);
        $this->assertSame('ignored', $result->unknown);
    }

    public function testMapsNestedDto(): void
    {
        $joinPoint = $this->makeJoinPoint([
            'name' => 'alice',
            'address' => ['city' => 'Shanghai'],
        ], ObjectNormalizerProbeDto::class);
        $result = (new ObjectNormalizerAspect())->process($joinPoint);

        $this->assertInstanceOf(ObjectNormalizerProbeAddressDto::class, $result->address);
        $this->assertSame('Shanghai', $result->address->city);
    }

    private function makeJoinPoint(array $data, string $type): ProceedingJoinPoint
    {
        return new ProceedingJoinPoint(
            fn () => null,
            'Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer',
            'denormalize',
            [
                'keys' => ['data' => $data, 'type' => $type],
                'order' => ['data', 'type'],
            ]
        );
    }
}

class ObjectNormalizerProbeDto
{
    public string $name = '';

    public int $age = 0;

    public string $unknown = '';

    public ?ObjectNormalizerProbeAddressDto $address = null;
}

class ObjectNormalizerProbeAddressDto
{
    public string $city = '';
}
