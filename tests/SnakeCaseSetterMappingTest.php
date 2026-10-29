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
 * 输入只接受声明主键，非主键直接忽略。
 *
 * @internal
 * @coversNothing
 */
class SnakeCaseSetterMappingTest extends AbstractTestCase
{
    public function testPlanDoesNotDuplicateDeclaredSnakeCaseProperty(): void
    {
        $mapper = $this->mapper();
        $mapper->simpleMap(['user_name' => 'ok'], new SnakeCaseSetterDto());

        $plan = $this->plan($mapper, SnakeCaseSetterDto::class);
        $this->assertCount(1, $plan['fields']);
        $this->assertSame('user_name', $plan['fields'][0]['primaryKey']);
    }

    public function testCamelCaseSetterKeyIsIgnored(): void
    {
        $dto = $this->mapper()->simpleMap(['userName' => 'ignored'], new SnakeCaseSetterDto());

        $this->assertSame('', $dto->getUserName());
    }

    public function testDeclaredSnakeCaseKeyWinsWhenEquivalentKeysAreBothPresent(): void
    {
        $dto = $this->mapper()->simpleMap([
            'user_name' => 'ok',
            'userName' => 'this-is-way-too-long',
        ], new SnakeCaseSetterDto());

        $this->assertSame('ok', $dto->getUserName());
    }

    public function testDeclaredSnakeCaseKeyWinsRegardlessOfInputOrder(): void
    {
        $dto = $this->mapper()->simpleMap([
            'userName' => 'this-is-way-too-long',
            'user_name' => 'ok',
        ], new SnakeCaseSetterDto());

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
