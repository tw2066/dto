<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Bail;
use Hyperf\DTO\Annotation\Validation\Integer;

/**
 * @internal
 * @coversNothing
 */
class BailTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $bail = new Bail('');
        $integer = new Integer('integer message');

        $this->assertPasses(
            ['age' => 25],
            ['age' => [$bail->getRule(), $integer->getRule()]],
            ['age.integer' => 'integer message']
        );
    }

    public function testBoundaryValues(): void
    {
        $bail = new Bail('');

        $this->assertPasses(
            ['age' => ''],
            ['age' => [$bail->getRule()]],
            ['age.bail' => '']
        );

        $this->assertPasses(
            ['age' => null],
            ['age' => [$bail->getRule()]],
            ['age.bail' => '']
        );
    }

    public function testConditionalSometimes(): void
    {
        $bail = new Bail('');

        $this->assertPasses(
            [],
            ['age' => ['sometimes', $bail->getRule()]],
            ['age.bail' => '']
        );
    }
}
