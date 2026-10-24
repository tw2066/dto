<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Aspect;

use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\DTO\Aspect\ObjectNormalizerAspect;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;

/**
 * @internal
 * @coversNothing
 */
class ObjectNormalizerAspectTest extends TestCase
{
    private SymfonySerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = new SymfonySerializer([
            new ObjectNormalizer(new ClassMetadataFactory(new AttributeLoader())),
        ]);
    }

    public function testGroupsAreForwardedToSymfony(): void
    {
        $dto = $this->denormalize(['userName' => 'alice', 'role' => 'admin'], ['groups' => ['api']]);

        $this->assertSame('alice', $dto->userName);
        $this->assertSame('', $dto->role);
    }

    public function testAllowExtraAttributesIsForwarded(): void
    {
        $this->expectException(ExtraAttributesException::class);
        $this->denormalize(
            ['userName' => 'alice', 'evil' => 'x'],
            [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false]
        );
    }

    public function testObjectToPopulateIsForwarded(): void
    {
        $existing = new ObjectNormalizerProbeDto();
        $existing->age = 99;

        $dto = $this->denormalize(
            ['userName' => 'alice'],
            [AbstractNormalizer::OBJECT_TO_POPULATE => $existing]
        );

        $this->assertSame($existing, $dto);
        $this->assertSame(99, $dto->age);
    }

    private function denormalize(array $data, array $context): ObjectNormalizerProbeDto
    {
        $joinPoint = new ProceedingJoinPoint(
            fn () => null,
            ObjectNormalizer::class,
            'denormalize',
            [
                'keys' => [
                    'data' => $data,
                    'type' => ObjectNormalizerProbeDto::class,
                    'format' => null,
                    'context' => $context,
                ],
                'order' => ['data', 'type', 'format', 'context'],
            ]
        );
        $joinPoint->pipe = fn (ProceedingJoinPoint $joinPoint) => $this->serializer->denormalize(...$joinPoint->getArguments());

        return (new ObjectNormalizerAspect())->process($joinPoint);
    }
}

class ObjectNormalizerProbeDto
{
    #[Groups(['api'])]
    public string $userName = '';

    public string $role = '';

    public int $age = 0;
}
