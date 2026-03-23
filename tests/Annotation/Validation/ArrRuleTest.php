<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\ArrRule;

/**
 * @internal
 * @coversNothing
 */
class ArrRuleTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new ArrRule('integer', 'arr rule message');

        $this->assertPasses(
            ['numbers' => [1, 2, 3]],
            ['numbers' => ['array'], 'numbers.*' => [$annotation->getRule()]],
            ['numbers.*.integer' => 'arr rule message']
        );

        $annotationString = new ArrRule('string', 'arr rule message');
        $this->assertPasses(
            ['names' => ['foo', 'bar']],
            ['names' => ['array'], 'names.*' => [$annotationString->getRule()]],
            ['names.*.string' => 'arr rule message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new ArrRule('integer', 'arr rule message');

        $this->assertFailsWithMessage(
            ['numbers' => [1, 'two', 3]],
            ['numbers' => ['array'], 'numbers.*' => [$annotation->getRule()]],
            ['numbers.*.integer' => 'arr rule message'],
            'numbers.1',
            'arr rule message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new ArrRule('integer', 'arr rule message');

        $this->assertPasses(
            ['numbers' => []],
            ['numbers' => ['array'], 'numbers.*' => [$annotation->getRule()]],
            ['numbers.*.integer' => 'arr rule message']
        );

        $this->assertPasses(
            ['numbers' => [0, -1, 999]],
            ['numbers' => ['array'], 'numbers.*' => [$annotation->getRule()]],
            ['numbers.*.integer' => 'arr rule message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new ArrRule('integer', 'arr rule message');

        $this->assertPasses(
            [],
            ['numbers' => ['sometimes', 'array'], 'numbers.*' => ['sometimes', $annotation->getRule()]],
            ['numbers.*.integer' => 'arr rule message']
        );
    }
}
