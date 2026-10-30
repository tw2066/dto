<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\JsonMapper;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class JsonMapperPropertyAccessTest extends TestCase
{
    public function testStaticPropertyIsNotMapped(): void
    {
        PropertyAccessStaticDto::$cache = ['clean' => true];
        try {
            $mapper = new JsonMapper();
            $mapper->bEnforceMapType = false;
            $dto = $mapper->simpleMap(['cache' => ['pwned' => true], 'name' => 'x'], new PropertyAccessStaticDto());

            $this->assertSame(['clean' => true], PropertyAccessStaticDto::$cache);
            $this->assertSame('x', $dto->name);
        } finally {
            PropertyAccessStaticDto::$cache = ['clean' => true];
        }
    }

    public function testReadonlyPropertyIsNotModified(): void
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $dto = $mapper->simpleMap(['id' => 'evil'], new PropertyAccessReadonlyDto());

        $this->assertSame('fixed', $dto->id);
    }
}

class PropertyAccessStaticDto
{
    public static array $cache = ['clean' => true];

    public string $name = '';
}

class PropertyAccessReadonlyDto
{
    public readonly string $id;

    public function __construct()
    {
        $this->id = 'fixed';
    }
}
