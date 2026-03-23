<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Ipv4;

/**
 * @internal
 * @coversNothing
 */
class Ipv4Test extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Ipv4('ipv4 message');

        $this->assertPasses(
            ['v' => '127.0.0.1'],
            ['v' => [$annotation->getRule()]],
            ['v.ipv4' => 'ipv4 message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Ipv4('ipv4 message');

        $this->assertFailsWithMessage(
            ['v' => '::1'],
            ['v' => [$annotation->getRule()]],
            ['v.ipv4' => 'ipv4 message'],
            'v',
            'ipv4 message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Ipv4('ipv4 message');

        $this->assertPasses(
            ['v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.ipv4' => 'ipv4 message']
        );

        $this->assertPasses(
            ['v' => null],
            ['v' => ['nullable', $annotation->getRule()]],
            ['v.ipv4' => 'ipv4 message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Ipv4('ipv4 message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.ipv4' => 'ipv4 message']
        );

        $this->assertFailsWithMessage(
            ['v' => '::1'],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.ipv4' => 'ipv4 message'],
            'v',
            'ipv4 message'
        );
    }
}
