<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\Annotation\Validation\Max;
use Hyperf\DTO\DtoCommon;
use Hyperf\DTO\DtoValidation;
use Hyperf\DTO\JsonMapper;
use Hyperf\DTO\Scan\PropertyEnum;
use Hyperf\DTO\Scan\PropertyManager;
use Hyperf\DTO\Scan\ValidationManager;

/**
 * 声明属性为 snake_case、setter 为 camelCase 时不得生成重复字段；
 * 等价键同时出现时以声明属性名为准。
 *
 * @internal
 * @coversNothing
 */
class SnakeCaseSetterMappingTest extends AbstractTestCase
{
    public function testPlanDoesNotDuplicateDeclaredSnakeCaseProperty(): void
    {
        $mapper = $this->mapper();
        $mapper->map(['user_name' => 'ok'], new SnakeCaseSetterDto());

        $plan = $this->plan($mapper, SnakeCaseSetterDto::class);
        $this->assertCount(1, $plan['fields']);
        $this->assertSame(['user_name'], array_keys($plan['keyMap']));
        $this->assertSame(['setUserName'], array_keys($plan['setterMap']));
    }

    public function testCamelCaseSetterKeyMapsToDeclaredSnakeCaseProperty(): void
    {
        $dto = $this->mapper()->map(['userName' => 'ok'], new SnakeCaseSetterDto());

        $this->assertSame('ok', $dto->getUserName());
    }

    public function testDeclaredSnakeCaseKeyWinsWhenEquivalentKeysAreBothPresent(): void
    {
        $dto = $this->mapper()->map([
            'user_name' => 'ok',
            'userName' => 'this-is-way-too-long',
        ], new SnakeCaseSetterDto());

        $this->assertSame('ok', $dto->getUserName());
    }

    public function testDeclaredSnakeCaseKeyWinsRegardlessOfInputOrder(): void
    {
        $dto = $this->mapper()->map([
            'userName' => 'this-is-way-too-long',
            'user_name' => 'ok',
        ], new SnakeCaseSetterDto());

        $this->assertSame('ok', $dto->getUserName());
    }

    public function testResolvedInputUsesDeclaredSnakeCaseKey(): void
    {
        $mapper = $this->mapper();
        $resolved = $mapper->resolveInput([
            'user_name' => 'ok',
            'userName' => 'this-is-way-too-long',
        ], SnakeCaseSetterDto::class);

        $this->assertSame(['user_name' => 'ok'], $resolved->data);

        $dto = $mapper->mapResolvedInput($resolved, new SnakeCaseSetterDto());
        $this->assertSame('ok', $dto->getUserName());
    }

    public function testValidationUsesDeclaredSnakeCaseValue(): void
    {
        $this->setContainerWithValidatorFactory();
        (new Max(5))->collectProperty(SnakeCaseSetterDto::class, 'user_name');
        (new ValidationManager())->generateValidation(SnakeCaseSetterDto::class, 'user_name');

        $dtoValidation = new DtoValidation(
            new PropertyManager(new DtoCommon(), new PropertyEnum()),
            new ValidationManager()
        );

        $dtoValidation->validate(SnakeCaseSetterDto::class, [
            'user_name' => 'ok',
            'userName' => 'this-is-way-too-long',
        ]);

        $this->assertTrue(true);
    }

    private function mapper(): JsonMapper
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;

        return $mapper;
    }

    private function plan(JsonMapper $mapper, string $className): array
    {
        $property = new \ReflectionProperty(JsonMapper::class, 'mappingPlans');
        $property->setAccessible(true);

        return $property->getValue($mapper)[$className];
    }
}

class SnakeCaseSetterDto
{
    private string $user_name = '';

    public function setUserName(string $userName): void
    {
        $this->user_name = $userName;
    }

    public function getUserName(): string
    {
        return $this->user_name;
    }
}
