<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\HexColor;

/**
 * @internal
 * @coversNothing
 */
class HexColorTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new HexColor('hex_color message');

        $this->assertPasses(
            ['color' => '#ffffff'],
            ['color' => [$annotation->getRule()]],
            ['color.hex_color' => 'hex_color message']
        );

        $this->assertPasses(
            ['color' => '#000000'],
            ['color' => [$annotation->getRule()]],
            ['color.hex_color' => 'hex_color message']
        );

        $this->assertPasses(
            ['color' => '#ff0000'],
            ['color' => [$annotation->getRule()]],
            ['color.hex_color' => 'hex_color message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new HexColor('hex_color message');

        $this->assertFailsWithMessage(
            ['color' => 'invalid-color'],
            ['color' => [$annotation->getRule()]],
            ['color.hex_color' => 'hex_color message'],
            'color',
            'hex_color message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new HexColor('hex_color message');

        $this->assertPasses(
            ['color' => ''],
            ['color' => [$annotation->getRule()]],
            ['color.hex_color' => 'hex_color message']
        );

        $this->assertFailsWithMessage(
            ['color' => 'a'],
            ['color' => [$annotation->getRule()]],
            ['color.hex_color' => 'hex_color message'],
            'color',
            'hex_color message'
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new HexColor('hex_color message');

        $this->assertPasses(
            [],
            ['color' => ['sometimes', $annotation->getRule()]],
            ['color.hex_color' => 'hex_color message']
        );

        $this->assertFailsWithMessage(
            ['color' => 'invalid-color'],
            ['color' => ['sometimes', $annotation->getRule()]],
            ['color.hex_color' => 'hex_color message'],
            'color',
            'hex_color message'
        );
    }
}
