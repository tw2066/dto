<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Ascii;

/**
 * @internal
 * @coversNothing
 */
class AsciiTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Ascii('ascii message');

        $this->assertPasses(
            ['v' => 'abc'],
            ['v' => [$annotation->getRule()]],
            ['v.ascii' => 'ascii message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Ascii('ascii message');

        $this->assertFailsWithMessage(
            ['v' => '中文'],
            ['v' => [$annotation->getRule()]],
            ['v.ascii' => 'ascii message'],
            'v',
            'ascii message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Ascii('ascii message');

        $this->assertPasses(
            ['v' => ''],
            ['v' => [$annotation->getRule()]],
            ['v.ascii' => 'ascii message']
        );

        $this->assertPasses(
            ['v' => 0],
            ['v' => [$annotation->getRule()]],
            ['v.ascii' => 'ascii message']
        );

        $this->assertPasses(
            ['v' => null],
            ['v' => [$annotation->getRule()]],
            ['v.ascii' => 'ascii message']
        );

        $this->assertPasses(
            ['v' => new class {
                public function __toString(): string
                {
                    return 'abc';
                }
            }],
            ['v' => [$annotation->getRule()]],
            ['v.ascii' => 'ascii message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Ascii('ascii message');

        $this->assertPasses(
            [],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.ascii' => 'ascii message']
        );

        $this->assertFailsWithMessage(
            ['v' => '中文'],
            ['v' => ['sometimes', $annotation->getRule()]],
            ['v.ascii' => 'ascii message'],
            'v',
            'ascii message'
        );
    }
}
