<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\DTO\Annotation\Validation\Missing;

/**
 * @internal
 * @coversNothing
 */
class MissingTest extends ValidationAnnotationTestCase
{
    public function testPasses(): void
    {
        $annotation = new Missing('missing message');

        $this->assertPasses(
            [],
            ['field' => [$annotation->getRule()]],
            ['field.missing' => 'missing message']
        );
    }

    public function testFailsWithMessage(): void
    {
        $annotation = new Missing('missing message');

        $this->assertFailsWithMessage(
            ['field' => 'value'],
            ['field' => [$annotation->getRule()]],
            ['field.missing' => 'missing message'],
            'field',
            'missing message'
        );
    }

    public function testBoundaryValues(): void
    {
        $annotation = new Missing('missing message');

        $this->assertPasses(
            ['other_field' => 'value'],
            ['field' => [$annotation->getRule()]],
            ['field.missing' => 'missing message']
        );
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Missing('missing message');

        $this->assertPasses(
            [],
            ['field' => ['sometimes', $annotation->getRule()]],
            ['field.missing' => 'missing message']
        );
    }
}
