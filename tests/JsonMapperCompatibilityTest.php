<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\JsonMapper;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class JsonMapperCompatibilityTest extends TestCase
{
    public function testAcronymSetterAcceptsDeclaredKeyWithoutAllowingArbitraryCase(): void
    {
        $mapper = $this->mapper();
        $this->assertSame(7, $mapper->map(['id' => 7], new CompatibilityAcronymDto())->getId());
        $this->assertSame(8, $mapper->map(['ID' => 8], new CompatibilityAcronymDto())->getId());
        $this->assertSame(0, $mapper->map(['Id' => 9], new CompatibilityAcronymDto())->getId());
    }

    public function testHyphenatedPropertyFallbackDoesNotAllowRawCaseVariants(): void
    {
        $mapper = $this->mapper();
        $dto = $mapper->map(['XRequestId' => 'ignored'], new CompatibilityHeaderDto());
        $this->assertSame('', $dto->xRequestId);

        $dto = $mapper->map(['x-request-id' => 'abc'], new CompatibilityHeaderDto());
        $this->assertSame('abc', $dto->xRequestId);

        $dto = $mapper->map(['X-Request-Id' => 'def'], new CompatibilityHeaderDto());
        $this->assertSame('def', $dto->xRequestId);

        $dto = $mapper->map(['XRequestId' => 'ignored-again'], new CompatibilityHeaderDto());
        $this->assertSame('', $dto->xRequestId);
    }

    private function mapper(): JsonMapper
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $mapper->bStrictNullTypes = false;
        return $mapper;
    }
}

class CompatibilityAcronymDto
{
    private int $id = 0;

    public function setID(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }
}

class CompatibilityHeaderDto
{
    public string $xRequestId = '';
}
