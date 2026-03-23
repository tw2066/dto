<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Ip;

/**
 * @internal
 * @coversNothing
 */
class IpTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Ip('ip message');

        $this->assertPasses(
            ['v' => '127.0.0.1'],
            ['v' => [$annotation->getRule()]],
            ['v.ip' => 'ip message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Ip('ip message');

        $this->assertFailsWithMessage(
            ['v' => 'not-an-ip'],
            ['v' => [$annotation->getRule()]],
            ['v.ip' => 'ip message'],
            'v',
            'ip message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Ip('ip message');

        $this->assertPasses(
            ['v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.ip' => 'ip message']
        );

        $this->assertFailsWithMessage(
            ['v' => 0],
            ['v' => [$annotation->getRule()]],
            ['v.ip' => 'ip message'],
            'v',
            'ip message'
        );

        $this->assertPasses(
            ['v' => null],
            ['v' => ['nullable', $annotation->getRule()]],
            ['v.ip' => 'ip message']
        );

        $this->assertFailsWithMessage(
            ['v' => []],
            ['v' => [$annotation->getRule()]],
            ['v.ip' => 'ip message'],
            'v',
            'ip message'
        );

        $this->assertFailsWithMessage(
            ['v' => (object) ['a' => 1]],
            ['v' => [$annotation->getRule()]],
            ['v.ip' => 'ip message'],
            'v',
            'ip message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Ip('ip message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.ip' => 'ip message']
        );

        $this->assertFailsWithMessage(
            ['v' => 'not-an-ip'],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.ip' => 'ip message'],
            'v',
            'ip message'
        );
    }
}
