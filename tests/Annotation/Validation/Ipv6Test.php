<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Ipv6;

/**
 * @internal
 * @coversNothing
 */
class Ipv6Test extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Ipv6('ipv6 message');

        $this->assertPasses(
            ['v' => '::1'],
            ['v' => [$annotation->getRule()]],
            ['v.ipv6' => 'ipv6 message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Ipv6('ipv6 message');

        $this->assertFailsWithMessage(
            ['v' => '127.0.0.1'],
            ['v' => [$annotation->getRule()]],
            ['v.ipv6' => 'ipv6 message'],
            'v',
            'ipv6 message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Ipv6('ipv6 message');

        $this->assertPasses(
            ['v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.ipv6' => 'ipv6 message']
        );

        $this->assertPasses(
            ['v' => null],
            ['v' => ['nullable', $annotation->getRule()]],
            ['v.ipv6' => 'ipv6 message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Ipv6('ipv6 message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.ipv6' => 'ipv6 message']
        );

        $this->assertFailsWithMessage(
            ['v' => '127.0.0.1'],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.ipv6' => 'ipv6 message'],
            'v',
            'ipv6 message'
        );
    }
}
