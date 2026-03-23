<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Timezone;

/**
 * @internal
 * @coversNothing
 */
class TimezoneTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Timezone('timezone message');

        $this->assertPasses(
            ['timezone' => 'Asia/Shanghai'],
            ['timezone' => [$annotation->getRule()]],
            ['timezone.timezone' => 'timezone message']
        );

        $this->assertPasses(
            ['timezone' => 'UTC'],
            ['timezone' => [$annotation->getRule()]],
            ['timezone.timezone' => 'timezone message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Timezone('timezone message');

        $this->assertFailsWithMessage(
            ['timezone' => 'Invalid/Timezone'],
            ['timezone' => [$annotation->getRule()]],
            ['timezone.timezone' => 'timezone message'],
            'timezone',
            'timezone message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Timezone('timezone message');

        $this->assertPasses(
            ['timezone' => 'America/New_York'],
            ['timezone' => [$annotation->getRule()]],
            ['timezone.timezone' => 'timezone message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Timezone('timezone message');

        $this->assertPasses(
            [],
            ['timezone' => ['sometimes', $annotation->getRule()]],
            ['timezone.timezone' => 'timezone message']
        );
    }
}
