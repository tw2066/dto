<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Unique;

/**
 * @internal
 * @coversNothing
 */
class UniqueTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Unique('users', 'email', null, null, [], 'unique message');

        $rule = $annotation->getRule();
        $this->assertNotNull($rule);
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Unique('users', 'email', null, null, [], 'unique message');

        $rule = $annotation->getRule();
        $this->assertNotNull($rule);
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Unique('users', 'email', 'id', null, [['status', '=', '1']], 'unique message');

        $rule = $annotation->getRule();
        $this->assertNotNull($rule);
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Unique('users', 'email', null, null, [], 'unique message');

        $this->assertPasses(
            [],
            ['email' => ['sometimes', $annotation->getRule()]],
            ['email.unique' => 'unique message']
        );
    }
}
