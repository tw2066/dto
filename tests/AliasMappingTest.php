<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\JsonMapper;
use Hyperf\DTO\Scan\PropertyAliasMappingManager;
use HyperfTest\DTO\Request\Address;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class AliasMappingTest extends TestCase
{
    protected function tearDown(): void
    {
    }

    public function testScan()
    {
        $jsonMapper = new JsonMapper();
        $jsonMapper->bEnforceMapType = false;
        PropertyAliasMappingManager::setAliasMapping(Address::class, 'username', 'name');
        $arr = ['username' => 'phpDto'];
        $address = $jsonMapper->map($arr, new Address());
        $this->assertSame('phpDto', $address->name);
    }
}
