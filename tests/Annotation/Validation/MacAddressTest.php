<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\MacAddress;

/**
 * @internal
 * @coversNothing
 */
class MacAddressTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new MacAddress('mac_address message');

        $this->assertPasses(
            ['v' => '00:11:22:33:44:55'],
            ['v' => [$annotation->getRule()]],
            ['v.mac_address' => 'mac_address message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new MacAddress('mac_address message');

        $this->assertFailsWithMessage(
            ['v' => 'not-mac'],
            ['v' => [$annotation->getRule()]],
            ['v.mac_address' => 'mac_address message'],
            'v',
            'mac_address message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new MacAddress('mac_address message');

        $this->assertPasses(
            ['v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.mac_address' => 'mac_address message']
        );

        $this->assertPasses(
            ['v' => null],
            ['v' => ['nullable', $annotation->getRule()]],
            ['v.mac_address' => 'mac_address message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new MacAddress('mac_address message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.mac_address' => 'mac_address message']
        );

        $this->assertFailsWithMessage(
            ['v' => 'not-mac'],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.mac_address' => 'mac_address message'],
            'v',
            'mac_address message'
        );
    }
}

