<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\DtoCommon;
use Hyperf\DTO\JsonMapper;
use Hyperf\DTO\Scan\PropertyEnum;
use Hyperf\DTO\Scan\PropertyManager;
use HyperfTest\DTO\Boundary\Dto\OrderDto;
use HyperfTest\DTO\Boundary\Models\Money;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class JsonMapperNamespaceTest extends TestCase
{
    public function testInheritedDocblockUsesDeclaringClassNamespace(): void
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $mapper->bStrictNullTypes = false;
        $dto = $mapper->map([
            'price' => ['currency' => 'CNY', 'amount' => 5],
            'prices' => [['currency' => 'USD', 'amount' => 6]],
        ], new OrderDto());

        $this->assertInstanceOf(Money::class, $dto->price);
        $this->assertSame('CNY', $dto->price->currency);
        $this->assertSame(5, $dto->price->amount);
        $this->assertInstanceOf(Money::class, $dto->prices[0]);
        $this->assertSame('USD', $dto->prices[0]->currency);

        $propertyManager = new PropertyManager(new DtoCommon(), new PropertyEnum());
        $property = $propertyManager->getProperty(OrderDto::class, 'prices');
        $this->assertSame(Money::class, $property->arrClassName);
    }
}
