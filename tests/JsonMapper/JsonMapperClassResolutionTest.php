<?php

declare(strict_types=1);

namespace HyperfTest\DTO\JsonMapper;

use DateTime;
use Hyperf\DTO\Exception\DtoException;
use Hyperf\DTO\JsonMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * 改编自 netresearch/jsonmapper（OSL-3.0）的 tests/ClassMapTest.php、tests/ClassFactoriesTest.php
 * 与 tests/ArrayTest.php 多态数组场景。
 *
 * 本项目 simpleMap() 保留 classMap / classFactories 的两种前导反斜杠键形式，
 * 并允许 classMap 把对象类型改写成标量类型或按元素动态选择子类。
 *
 * @internal
 * @coversNothing
 */
class JsonMapperClassResolutionTest extends TestCase
{
    public function testClassMapCanMapObjectTypeToScalar(): void
    {
        $mapper = $this->mapper();
        $mapper->bStrictObjectTypeChecking = false;
        $mapper->classMap[UpstreamResolvePlain::class] = 'string';

        $dto = $mapper->simpleMap(['value' => 'foo'], new UpstreamResolveDto());

        $this->assertIsString($dto->value);
        $this->assertSame('foo', $dto->value);
    }

    public function testClassMapCallbackReceivesOriginalTypeAndValue(): void
    {
        $mapper = $this->mapper();
        $mapper->bStrictObjectTypeChecking = false;
        $received = [];
        $mapper->classMap[UpstreamResolvePlain::class] = static function ($type, $value) use (&$received) {
            $received = [$type, $value];
            return DateTime::class;
        };

        $dto = $mapper->simpleMap([
            'value' => '2016-04-14T23:15:42+02:00',
        ], new UpstreamResolveDto());

        $this->assertInstanceOf(DateTime::class, $dto->value);
        $this->assertSame('2016-04-14T23:15:42+02:00', $dto->value->format('c'));
        $this->assertSame(
            ltrim(UpstreamResolvePlain::class, '\\'),
            ltrim($received[0], '\\')
        );
        $this->assertSame('2016-04-14T23:15:42+02:00', $received[1]);
    }

    #[DataProvider('leadingSlashKeyProvider')]
    public function testClassMapAcceptsBothLeadingSlashKeyForms(string $registeredKey): void
    {
        $mapper = $this->mapper();
        $mapper->bStrictObjectTypeChecking = false;
        $mapper->classMap[$registeredKey] = DateTime::class;

        $dto = $mapper->simpleMap(['value' => '2026-06-05'], new UpstreamResolveDto());

        $this->assertInstanceOf(DateTime::class, $dto->value);
        $this->assertSame('2026-06-05', $dto->value->format('Y-m-d'));
    }

    public static function leadingSlashKeyProvider(): array
    {
        return [
            'without leading slash' => [UpstreamResolvePlain::class],
            'with leading slash' => ['\\' . UpstreamResolvePlain::class],
        ];
    }

    #[DataProvider('dateTimeKeyProvider')]
    public function testClassFactoriesAcceptBothLeadingSlashKeyForms(string $registeredKey): void
    {
        $mapper = $this->mapper();
        $mapper->bStrictObjectTypeChecking = false;
        $mapper->classFactories[$registeredKey] = static fn ($value) => new DateTime($value);

        $dto = $mapper->simpleMap(['value' => '2026-06-05'], new UpstreamResolveDateTimeDto());

        $this->assertInstanceOf(DateTime::class, $dto->value);
        $this->assertSame('2026-06-05', $dto->value->format('Y-m-d'));
    }

    public static function dateTimeKeyProvider(): array
    {
        return [
            'without leading slash' => ['DateTime'],
            'with leading slash' => ['\DateTime'],
        ];
    }

    public function testClassFactoryErrorsAreNotSwallowed(): void
    {
        $mapper = $this->mapper();
        $mapper->classFactories['DateTime'] = static function (): DateTime {
            throw new RuntimeException('Invalid date pattern');
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid date pattern');

        $mapper->simpleMap(['value' => '05/06/2026'], new UpstreamResolveDateTimeDto());
    }

    public function testClassMapCanConvertArraySubtypeToScalar(): void
    {
        $mapper = $this->mapper();
        $mapper->classMap[DateTime::class] = 'string';

        $dto = $mapper->simpleMap([
            'items' => ['2019-03-23'],
        ], new UpstreamResolveDateTimeArrayDto());

        $this->assertSame(['2019-03-23'], $dto->items);
    }

    public function testClassMapCallbackSelectsPolymorphicArrayElements(): void
    {
        $mapper = $this->mapper();
        $mapper->classMap[UpstreamResolveAnimal::class] = static function ($type, $value) {
            return $value->kind === 'cat'
                ? UpstreamResolveCat::class
                : UpstreamResolveFish::class;
        };

        $dto = $mapper->simpleMap([
            'animals' => [
                (object) ['kind' => 'cat', 'name' => 'Lion'],
                (object) ['kind' => 'fish', 'name' => 'Clown Fish'],
            ],
        ], new UpstreamResolveZooDto());

        $this->assertCount(2, $dto->animals);
        $this->assertInstanceOf(UpstreamResolveCat::class, $dto->animals[0]);
        $this->assertSame('Lion', $dto->animals[0]->name);
        $this->assertInstanceOf(UpstreamResolveFish::class, $dto->animals[1]);
        $this->assertSame('Clown Fish', $dto->animals[1]->name);
    }

    /**
     * 文档期静态解析：callable 的分派结果依赖运行时值，不能执行，也不能用 null 调用。
     */
    public function testResolveMappedClassSkipsCallableInDocumentationMode(): void
    {
        $mapper = $this->mapper();
        $mapper->classMap[UpstreamResolveAnimal::class] = static function (string $type, array $value): string {
            return UpstreamResolveCat::class;
        };

        $this->assertNull($mapper->resolveMappedClass(UpstreamResolveAnimal::class, null, false));
        // 运行时模式仍保留原有行为：按实际值执行 callable。
        $this->assertSame(
            UpstreamResolveCat::class,
            $mapper->resolveMappedClass(UpstreamResolveAnimal::class, ['kind' => 'cat'])
        );
    }

    /**
     * 标量 classMap 目标没有 schema 可生成，文档期应返回 null 由调用方降级为 object。
     */
    public function testResolveMappedClassTreatsScalarTargetAsUnresolvedInDocumentationMode(): void
    {
        $mapper = $this->mapper();
        $mapper->classMap[DateTime::class] = 'string';

        $this->assertNull($mapper->resolveMappedClass(DateTime::class, null, false));
    }

    public function testResolveMappedClassResolvesStaticTargetInDocumentationMode(): void
    {
        $mapper = $this->mapper();
        $mapper->classMap[UpstreamResolveAnimal::class] = UpstreamResolveCat::class;

        $this->assertSame(
            UpstreamResolveCat::class,
            $mapper->resolveMappedClass(UpstreamResolveAnimal::class, null, false)
        );
    }

    /**
     * 未知类目标属于配置错误，文档期仍需显式失败，不能静默降级。
     */
    public function testResolveMappedClassStillRejectsUnknownClassTargetInDocumentationMode(): void
    {
        $mapper = $this->mapper();
        $mapper->classMap[UpstreamResolvePlain::class] = 'Missing\PaymentClass';

        $this->expectException(DtoException::class);
        $this->expectExceptionMessage('classMap target [Missing\PaymentClass]');

        $mapper->resolveMappedClass(UpstreamResolvePlain::class, null, false);
    }

    private function mapper(): JsonMapper
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $mapper->bStrictNullTypes = false;
        return $mapper;
    }
}

class UpstreamResolvePlain
{
    /** @var string */
    public $pStr = '';
}

class UpstreamResolveDto
{
    /** @var UpstreamResolvePlain */
    public $value;
}

class UpstreamResolveDateTimeDto
{
    /** @var DateTime */
    public $value;
}

class UpstreamResolveDateTimeArrayDto
{
    /** @var DateTime[] */
    public array $items = [];
}

abstract class UpstreamResolveAnimal
{
    public string $name = '';
}

class UpstreamResolveCat extends UpstreamResolveAnimal
{
}

class UpstreamResolveFish extends UpstreamResolveAnimal
{
}

class UpstreamResolveZooDto
{
    /** @var UpstreamResolveAnimal[] */
    public array $animals = [];
}
