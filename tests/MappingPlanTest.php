<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\Annotation\ArrayType;
use Hyperf\DTO\Annotation\JSONField;
use Hyperf\DTO\JsonMapper;
use Hyperf\DTO\Mapping\MappingContext;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class MappingPlanTest extends TestCase
{
    public function testPlanIsBuiltOnceAndDoesNotContainRequestKeys(): void
    {
        $mapper = $this->mapper();
        $mapper->simpleMap(['name' => 'first', 'unknown_a' => 1], new PlanProbeDto());
        $mapper->simpleMap(['name' => 'second', 'unknown_b' => 2], new PlanProbeDto());

        $this->assertSame(1, $mapper->buildCounts[PlanProbeDto::class]);
        $plan = $this->plans($mapper)[PlanProbeDto::class];
        $this->assertCount(1, $plan['fields']);
        $this->assertSame('name', $plan['fields'][0]['primaryKey']);
    }

    public function testOnlyPrimaryKeyIsAccepted(): void
    {
        $mapper = $this->mapper();
        $dto = $mapper->simpleMap(['userName' => 'ok'], new PlanSetterDto());
        $this->assertSame('ok', $dto->getUserName());

        foreach (['user_name', 'user-name', ' userName', 'UserName'] as $key) {
            $dto = $mapper->simpleMap([$key => 'ignored'], new PlanSetterDto());
            $this->assertSame('', $dto->getUserName());
        }

        $this->assertSame(1, $mapper->buildCounts[PlanSetterDto::class]);
        $plan = $this->plans($mapper)[PlanSetterDto::class];
        $this->assertSame('userName', $plan['fields'][0]['primaryKey']);
        $this->assertSame([], $this->legacyInspectedCache($mapper));
    }

    public function testPrimaryKeyWinsWhenNonPrimaryKeyAlsoPresent(): void
    {
        $mapper = $this->mapper();

        $dto = $mapper->simpleMap([
            'userName' => 'declared-value',
            'user_name' => 'variant-value',
        ], new PlanSetterDto());
        $this->assertSame('declared-value', $dto->getUserName());

        $dto = $mapper->simpleMap([
            'user_name' => 'variant-value',
            'userName' => 'declared-value',
        ], new PlanSetterDto());
        $this->assertSame('declared-value', $dto->getUserName());
    }

    public function testHyphenatedAliasIsAcceptedAsExactPrimaryKey(): void
    {
        // 4.0 只按 primaryKey 精确读取，连字符别名不再有 safe key 歧义
        $dto = $this->mapper()->simpleMap([
            'user-Name' => 'alias-value',
            'user_name' => 'declared-value',
        ], new PlanVariantCollisionDto());
        $this->assertSame('alias-value', $dto->other);
        $this->assertSame('declared-value', $dto->getUserName());

        // 非 primaryKey 变体（字段名、大小写变体）仍被忽略
        $dto = $this->mapper()->simpleMap(['other' => 'x', 'userName' => 'y'], new PlanVariantCollisionDto());
        $this->assertSame('', $dto->other);
        $this->assertSame('', $dto->getUserName());
    }

    public function testNestedPathAliasesAreRejected(): void
    {
        foreach ([PlanDotAliasDto::class, PlanWildcardAliasDto::class] as $className) {
            try {
                $this->mapper()->simpleMap([], new $className());
                $this->fail("Expected nested path alias to fail: {$className}");
            } catch (\Hyperf\DTO\Exception\DtoException $exception) {
                $this->assertStringContainsString('nested paths', $exception->getMessage());
            }
        }
    }

    public function testAliasCollisionWithAnotherPropertyFails(): void
    {
        $cases = [
            PlanAliasCollisionDto::class => '[userName] and [user_name]',
            PlanAliasCollisionReverseDto::class => '[user_name] and [userName]',
        ];
        foreach ($cases as $className => $fields) {
            try {
                $this->mapper()->simpleMap([], new $className());
                $this->fail("Expected alias collision to fail: {$className}");
            } catch (\Hyperf\DTO\Exception\DtoException $exception) {
                $this->assertSame(
                    "Input key [user_name] for class {$className} is ambiguous between fields {$fields}; "
                    . 'each field must have a unique property or alias key',
                    $exception->getMessage()
                );
            }
        }
    }

    public function testDuplicateAliasCollisionFails(): void
    {
        $this->expectException(\Hyperf\DTO\Exception\DtoException::class);
        $this->expectExceptionMessage(
            'Input key [user_name] for class ' . PlanDuplicateAliasDto::class
            . ' is ambiguous between fields [firstName] and [secondName]; '
            . 'each field must have a unique property or alias key'
        );

        $this->mapper()->simpleMap([], new PlanDuplicateAliasDto());
    }

    public function testAliasPrimaryKeyWinsWhenPropertyNameAlsoPresent(): void
    {
        $dto = $this->mapper()->simpleMap([
            'user_name' => 'alias-value',
            'userName' => 'property-value',
        ], new PlanAliasDto());

        $this->assertSame('alias-value', $dto->userName);

        $dto = $this->mapper()->simpleMap([
            'userName' => 'property-value',
            'user_name' => 'alias-value',
        ], new PlanAliasDto());

        $this->assertSame('alias-value', $dto->userName);
    }

    public function testNonPrimaryPropertyValueIsIgnored(): void
    {
        $dto = $this->mapper()->simpleMap([
            'userName' => ['invalid'],
            'user_name' => 'alias-value',
        ], new PlanAliasDto());

        $this->assertSame('alias-value', $dto->userName);
    }

    public function testEquivalentKeysDoNotBlockEarlierFields(): void
    {
        $dto = new PlanVariantConflictOrderDto();
        $this->mapper()->simpleMap([
            'first' => 'changed',
            'userName' => 'declared-value',
            'UserName' => 'variant-value',
        ], $dto);

        $this->assertSame('changed', $dto->first);
        $this->assertSame('declared-value', $dto->getUserName());
    }

    public function testSingleNodeKeyLimitIsEnforced(): void
    {
        $data = [];
        for ($i = 0; $i < 1025; ++$i) {
            $data['key_' . $i] = $i;
        }

        $this->expectException(\JsonMapper_Exception::class);
        $this->expectExceptionMessage('maximum is 1024');
        $this->mapper()->simpleMap($data, new PlanProbeDto());
    }

    public function testTotalKeyBudgetIsSharedAcrossRootNodes(): void
    {
        $mapper = $this->mapper();
        $context = new MappingContext();
        $context->totalKeys = MappingContext::MAX_TOTAL_KEYS - 1;
        $mapper->simpleMap(['name' => 'first'], new PlanProbeDto(), $context);

        $this->expectException(\JsonMapper_Exception::class);
        $this->expectExceptionMessage('maximum is 65536');
        $mapper->simpleMap(['name' => 'second'], new PlanProbeDto(), $context);
    }

    public function testNestedDepthLimitIsEnforced(): void
    {
        $input = ['name' => 'leaf'];
        for ($i = 0; $i < 101; ++$i) {
            $input = ['name' => 'node', 'child' => $input];
        }

        $this->expectException(\JsonMapper_Exception::class);
        $this->expectExceptionMessage('nesting too deep');
        $this->mapper()->simpleMap($input, new PlanNestedDto());
    }

    public function testUnknownKeysAreIgnoredByDefault(): void
    {
        $mapper = $this->mapper();
        $dto = $mapper->simpleMap(['name' => 'ok', 'unknown' => 1], new PlanProbeDto());

        $this->assertSame('ok', $dto->name);
        $this->assertSame(1, $mapper->buildCounts[PlanProbeDto::class]);
    }

    public function testMapHandlesNestedAliasesAndNullItemsWithSamePlans(): void
    {
        $mapper = $this->mapper();
        $dto = $mapper->simpleMap([
            'child_data' => ['display_name' => 'child'],
            'item_list' => [['display_name' => 'first'], null],
            'setter_items' => [['display_name' => 'second']],
        ], new PlanResolvedParentDto());

        $this->assertSame('child', $dto->child->name);
        $this->assertSame('first', $dto->items[0]->name);
        $this->assertNull($dto->items[1]);
        $this->assertSame('second', $dto->getSetterItems()[0]->name);
        $this->assertSame(1, $mapper->buildCounts[PlanResolvedParentDto::class]);
        $this->assertSame(1, $mapper->buildCounts[PlanResolvedChildDto::class]);
    }

    public function testMapUsesClassMappedNestedType(): void
    {
        $mapper = $this->mapper();
        $mapper->classMap[PlanDeclaredChildDto::class] = PlanMappedChildDto::class;
        $dto = $mapper->simpleMap([
            'child' => ['display_name' => 'mapped'],
        ], new PlanClassMapParentDto());

        $this->assertInstanceOf(PlanMappedChildDto::class, $dto->child);
        $this->assertSame('mapped', $dto->child->title);
    }

    public function testMapUsesClassFactoryNestedType(): void
    {
        $mapper = $this->mapper();
        $mapper->classFactories[PlanFactoryBaseDto::class] = static fn () => new PlanFactoryChildDto();

        $dto = $mapper->simpleMap([
            'child' => ['display_name' => 'factory'],
        ], new PlanFactoryParentDto());

        $this->assertInstanceOf(PlanFactoryChildDto::class, $dto->child);
        $this->assertSame('factory', $dto->child->title);
    }

    public function testMapUsesClassMapForInterfaceProperty(): void
    {
        $mapper = $this->mapper();
        $mapper->classMap[PlanShapeInterface::class] = PlanCircleDto::class;

        $dto = $mapper->simpleMap([
            'shape' => ['radius_cm' => 5],
        ], new PlanShapeParentDto());

        $this->assertInstanceOf(PlanCircleDto::class, $dto->shape);
        $this->assertSame(5, $dto->shape->radiusCm);
    }

    public function testMapUsesClassMapWithLeadingSlash(): void
    {
        $mapper = $this->mapper();
        $mapper->classMap['\\' . PlanShapeInterface::class] = PlanCircleDto::class;

        $dto = $mapper->simpleMap([
            'shape' => ['radius_cm' => 5],
        ], new PlanShapeParentDto());

        $this->assertInstanceOf(PlanCircleDto::class, $dto->shape);
        $this->assertSame(5, $dto->shape->radiusCm);
    }

    public function testMapUsesPlanTypeForVisibilityOnlyField(): void
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $mapper->bIgnoreVisibility = true;

        $dto = $mapper->simpleMap([
            'child' => ['display_name' => 'hidden'],
        ], new PlanHiddenParentDto());

        $this->assertSame('hidden', $dto->getChild()?->title);
    }

    public function testTraversableInputIsReadLikeArrayInput(): void
    {
        $mapper = $this->mapper();
        $dto = $mapper->simpleMap(new \ArrayObject(['name' => 'traversable']), new PlanProbeDto());
        $this->assertSame('traversable', $dto->name);

        $dto = $mapper->simpleMap(new \ArrayObject(['user_name' => 'resolved']), new PlanAliasDto());
        $this->assertSame('resolved', $dto->userName);
    }

    public function testMapSupportsMultiDimensionalClassArrays(): void
    {
        $mapper = $this->mapper();
        $input = ['groups' => [[['display_name' => 'nested']]]];
        $dto = $mapper->simpleMap($input, new PlanMultiArrayDto());

        $this->assertSame('nested', $dto->groups[0][0]->name);
    }

    public function testDynamicClassMapUsesOriginalNestedValueOnlyOnce(): void
    {
        $mapper = $this->mapper();
        $calls = 0;
        $mapper->classMap[PlanDynamicBaseDto::class] = static function ($type, $value) use (&$calls) {
            ++$calls;
            return $value instanceof \stdClass ? PlanDynamicADto::class : PlanDynamicBDto::class;
        };
        $dto = $mapper->simpleMap([
            'child' => (object) ['display_name' => 'dynamic'],
        ], new PlanDynamicParentDto());

        $this->assertInstanceOf(PlanDynamicADto::class, $dto->child);
        $this->assertSame('dynamic', $dto->child->value);
        $this->assertSame(1, $calls);
    }

    public function testArrayTypeAttributeSurvivesArraySetterInMappingPlan(): void
    {
        $mapper = $this->mapper();
        $input = ['items' => [['name' => 'ok']]];

        $dto = $mapper->simpleMap($input, new PlanArrayTypeSetterDto());
        $this->assertInstanceOf(PlanArrayTypeItemDto::class, $dto->getItems()[0]);
        $this->assertSame('ok', $dto->getItems()[0]->name);
    }

    public function testClassFactoryIsSelectedOnceDuringMapping(): void
    {
        $mapper = $this->mapper();
        $calls = 0;
        $mapper->classFactories[PlanDynamicBaseDto::class] = static function () use (&$calls) {
            return ++$calls % 2 === 1 ? new PlanDynamicADto() : new PlanDynamicBDto();
        };

        $dto = $mapper->simpleMap([
            'child' => ['display_name' => 'factory'],
        ], new PlanDynamicParentDto());

        $this->assertSame(1, $calls);
        $this->assertInstanceOf(PlanDynamicADto::class, $dto->child);
        $this->assertSame('factory', $dto->child->value);
    }

    public function testMapKeepsRawValuesWhenClassMapReturnsNull(): void
    {
        $mapper = $this->mapper();
        $mapper->classMap[PlanDeclaredChildDto::class] = static fn () => null;
        $input = [
            'child' => ['name' => 'child'],
            'items' => [['name' => 'first']],
        ];

        $dto = $mapper->simpleMap($input, new PlanNullClassMapParentDto());
        $this->assertSame($input['child'], $dto->child);
        $this->assertSame($input['items'], $dto->items);
    }

    public function testIgnoreVisibilityDoesNotAcceptHyphenatedVariant(): void
    {
        $mapper = $this->mapper();
        $mapper->bIgnoreVisibility = true;

        $dto = $mapper->simpleMap(['user-name' => 'ignored'], new PlanPrivateHyphenDto());

        $this->assertSame('', $dto->getUserName());
    }

    public function testIgnoreVisibilityIncludesParentPrivateProperties(): void
    {
        $mapper = $this->mapper();
        $mapper->bIgnoreVisibility = true;

        $dto = $mapper->simpleMap(['secret' => 'inherited'], new PlanChildPrivateDto());

        $this->assertSame('inherited', $dto->getSecret());
    }

    public function testChangingVisibilityRebuildsAccessorPlans(): void
    {
        $mapper = $this->mapper();
        $mapper->simpleMap(['a' => 'first'], new PlanVisibilityDto());
        $mapper->bIgnoreVisibility = true;

        $dto = $mapper->simpleMap(['b' => 'second'], new PlanVisibilityDto());

        $this->assertSame('second', $dto->getB());
    }

    public function testSetterWithoutDeclaredPropertyIsMapped(): void
    {
        $dto = $this->mapper()->simpleMap(['name' => 'setter-only'], new PlanSetterOnlyDto());

        $this->assertSame('setter-only', $dto->stored);
    }

    private function mapper(): CountingPlanJsonMapper
    {
        $mapper = new CountingPlanJsonMapper();
        $mapper->bEnforceMapType = false;
        $mapper->bStrictNullTypes = false;
        $mapper->bStrictNullTypesInArrays = false;
        return $mapper;
    }

    private function plans(JsonMapper $mapper): array
    {
        $property = new \ReflectionProperty(JsonMapper::class, 'mappingPlans');
        $property->setAccessible(true);
        return $property->getValue($mapper);
    }

    private function legacyInspectedCache(JsonMapper $mapper): array
    {
        $property = new \ReflectionProperty(\JsonMapper::class, 'arInspectedClasses');
        $property->setAccessible(true);
        return $property->getValue($mapper);
    }
}

class PlanProbeDto
{
    public string $name = '';
}

abstract class PlanFactoryBaseDto
{
}

class PlanFactoryChildDto extends PlanFactoryBaseDto
{
    #[JSONField('display_name')]
    public string $title = '';
}

class PlanFactoryParentDto
{
    /** @var PlanFactoryBaseDto */
    public PlanFactoryBaseDto $child;
}

interface PlanShapeInterface
{
}

class PlanCircleDto implements PlanShapeInterface
{
    #[JSONField('radius_cm')]
    public int $radiusCm = 0;
}

class PlanShapeParentDto
{
    public ?PlanShapeInterface $shape = null;
}

class PlanHiddenChildDto
{
    #[JSONField('display_name')]
    public string $title = '';
}

class PlanHiddenParentDto
{
    /** @var PlanHiddenChildDto */
    private $child;

    public function getChild(): ?PlanHiddenChildDto
    {
        return $this->child ?? null;
    }
}

class PlanSetterDto
{
    private string $userName = '';

    public function setUserName(string $userName): void
    {
        $this->userName = $userName;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }
}

class PlanAliasDto
{
    #[JSONField('user_name')]
    public string $userName = '';
}

class PlanDotAliasDto
{
    #[JSONField('user.name')]
    public string $userName = '';
}

class PlanWildcardAliasDto
{
    #[JSONField('users.*')]
    public string $userName = '';
}

class PlanVariantCollisionDto
{
    #[JSONField('user-Name')]
    public string $other = '';

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

class PlanAliasCollisionDto
{
    #[JSONField('user_name')]
    public string $userName = '';

    public string $user_name = '';

    public int $age = 0;
}

class PlanAliasCollisionReverseDto
{
    public string $user_name = '';

    #[JSONField('user_name')]
    public string $userName = '';

    public int $age = 0;
}

class PlanDuplicateAliasDto
{
    #[JSONField('user_name')]
    public string $firstName = '';

    #[JSONField('user_name')]
    public string $secondName = '';

    public int $age = 0;
}

class PlanVariantConflictOrderDto
{
    public string $first = 'default';
    private string $userName = '';

    public function setUserName(string $userName): void
    {
        $this->userName = $userName;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }
}

class PlanNestedDto
{
    public string $name = '';

    public ?PlanNestedDto $child = null;
}

class PlanResolvedChildDto
{
    #[JSONField('display_name')]
    public string $name = '';
}

class PlanResolvedParentDto
{
    #[JSONField('child_data')]
    public ?PlanResolvedChildDto $child = null;

    /**
     * @var PlanResolvedChildDto[]
     */
    #[JSONField('item_list')]
    public array $items = [];

    /**
     * @var PlanResolvedChildDto[]
     */
    #[JSONField('setter_items')]
    private array $setterItems = [];

    /**
     * @param PlanResolvedChildDto[] $items
     */
    public function setSetterItems(array $items): void
    {
        $this->setterItems = $items;
    }

    public function getSetterItems(): array
    {
        return $this->setterItems;
    }
}

class PlanDeclaredChildDto
{
    public string $name = '';
}

class PlanMappedChildDto extends PlanDeclaredChildDto
{
    #[JSONField('display_name')]
    public string $title = '';
}

class PlanClassMapParentDto
{
    public ?PlanDeclaredChildDto $child = null;
}

class PlanNullClassMapParentDto
{
    /** @var PlanDeclaredChildDto */
    public $child;

    /** @var PlanDeclaredChildDto[] */
    public array $items = [];
}

class PlanArrayTypeItemDto
{
    public string $name = '';
}

class PlanArrayTypeSetterDto
{
    #[ArrayType(PlanArrayTypeItemDto::class)]
    private array $items = [];

    public function getItems(): array
    {
        return $this->items;
    }

    public function setItems(array $items): void
    {
        $this->items = $items;
    }
}

class PlanMultiArrayDto
{
    /** @var PlanResolvedChildDto[][] */
    public array $groups = [];
}

class PlanDynamicBaseDto {}

class PlanDynamicADto extends PlanDynamicBaseDto
{
    #[JSONField('display_name')]
    public string $value = '';
}

class PlanDynamicBDto extends PlanDynamicBaseDto
{
    public string $other = '';
}

class PlanDynamicParentDto
{
    public ?PlanDynamicBaseDto $child = null;
}

class PlanParentPrivateDto
{
    private string $secret = 'default';

    public function getSecret(): string
    {
        return $this->secret;
    }
}

class PlanChildPrivateDto extends PlanParentPrivateDto {}

class PlanVisibilityDto
{
    public string $a = '';

    private string $b = '';

    public function getB(): string
    {
        return $this->b;
    }
}

class PlanPrivateHyphenDto
{
    private string $userName = '';

    public function getUserName(): string
    {
        return $this->userName;
    }
}

class PlanSetterOnlyDto
{
    public string $stored = '';

    public function setName(string $name): void
    {
        $this->stored = $name;
    }
}

class CountingPlanJsonMapper extends JsonMapper
{
    public array $buildCounts = [];

    protected function buildMappingPlan(string $className): array
    {
        $this->buildCounts[$className] = ($this->buildCounts[$className] ?? 0) + 1;
        return parent::buildMappingPlan($className);
    }
}
