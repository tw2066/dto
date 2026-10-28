<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\Annotation\ArrayType;
use Hyperf\DTO\Annotation\JSONField;
use Hyperf\DTO\JsonMapper;
use Hyperf\DTO\Mapping\MappingContext;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @internal
 * @coversNothing
 */
class MappingPlanTest extends TestCase
{
    public function testPlanIsBuiltOnceAndDoesNotContainRequestKeys(): void
    {
        $mapper = $this->mapper();
        $mapper->map(['name' => 'first', 'unknown_a' => 1], new PlanProbeDto());
        $mapper->map(['name' => 'second', 'unknown_b' => 2], new PlanProbeDto());

        $this->assertSame(1, $mapper->buildCounts[PlanProbeDto::class]);
        $plan = $this->plans($mapper)[PlanProbeDto::class];
        $this->assertSame(['name'], array_keys($plan['keyMap']));
        $this->assertArrayNotHasKey('unknown_a', $plan['keyMap']);
        $this->assertArrayNotHasKey('unknown_b', $plan['keyMap']);
    }

    public function testVariantKeysUseSamePlanWithoutRawKeyCache(): void
    {
        $mapper = $this->mapper();
        foreach (['user_name', 'user-name', ' userName', 'UserName'] as $key) {
            $dto = $mapper->map([$key => 'ok'], new PlanSetterDto());
            $this->assertSame('ok', $dto->getUserName());
        }

        $this->assertSame(1, $mapper->buildCounts[PlanSetterDto::class]);
        $plan = $this->plans($mapper)[PlanSetterDto::class];
        $this->assertSame(['userName'], array_keys($plan['keyMap']));
        $this->assertSame(['setUserName'], array_keys($plan['setterMap']));
        $this->assertSame([], $this->legacyInspectedCache($mapper));
    }

    public function testPropertyNameWinsOverEquivalentVariant(): void
    {
        $mapper = $this->mapper();

        $dto = $mapper->map([
            'userName' => 'declared-value',
            'user_name' => 'variant-value',
        ], new PlanSetterDto());
        $this->assertSame('declared-value', $dto->getUserName());

        $dto = $mapper->map([
            'user_name' => 'variant-value',
            'userName' => 'declared-value',
        ], new PlanSetterDto());
        $this->assertSame('declared-value', $dto->getUserName());
    }

    public function testAliasAndSafeAliasAmbiguityFails(): void
    {
        $this->expectException(\Hyperf\DTO\Exception\DtoException::class);
        $this->expectExceptionMessage(
            'JSONField alias [user-Name] for class ' . PlanVariantCollisionDto::class
            . '::other is ambiguous with safe key [UserName]; use an alias without hyphens'
        );

        $this->mapper()->map([], new PlanVariantCollisionDto());
    }

    public function testAliasCollisionWithAnotherPropertyFails(): void
    {
        $cases = [
            PlanAliasCollisionDto::class => '[userName] and [user_name]',
            PlanAliasCollisionReverseDto::class => '[user_name] and [userName]',
        ];
        foreach ($cases as $className => $fields) {
            try {
                $this->mapper()->map([], new $className());
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

        $this->mapper()->map([], new PlanDuplicateAliasDto());
    }

    public function testAliasInputWinsOverPropertyName(): void
    {
        $dto = $this->mapper()->map([
            'user_name' => 'alias-value',
            'userName' => 'property-value',
        ], new PlanAliasDto());

        $this->assertSame('alias-value', $dto->userName);

        $dto = $this->mapper()->map([
            'userName' => 'property-value',
            'user_name' => 'alias-value',
        ], new PlanAliasDto());

        $this->assertSame('alias-value', $dto->userName);
    }

    public function testLowerPriorityInvalidValueDoesNotBlockAlias(): void
    {
        $dto = $this->mapper()->map([
            'userName' => ['invalid'],
            'user_name' => 'alias-value',
        ], new PlanAliasDto());

        $this->assertSame('alias-value', $dto->userName);
    }

    public function testEquivalentKeysDoNotBlockEarlierFields(): void
    {
        $dto = new PlanVariantConflictOrderDto();
        $this->mapper()->map([
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
        $this->mapper()->map($data, new PlanProbeDto());
    }

    public function testTotalKeyBudgetIsSharedAcrossRootNodes(): void
    {
        $mapper = $this->mapper();
        $context = new MappingContext();
        $context->totalKeys = MappingContext::MAX_TOTAL_KEYS - 1;
        $mapper->mapWithContext(['name' => 'first'], new PlanProbeDto(), $context);

        $this->expectException(\JsonMapper_Exception::class);
        $this->expectExceptionMessage('maximum is 65536');
        $mapper->mapWithContext(['name' => 'second'], new PlanProbeDto(), $context);
    }

    public function testNestedDepthLimitIsEnforced(): void
    {
        $input = ['name' => 'leaf'];
        for ($i = 0; $i < 101; ++$i) {
            $input = ['name' => 'node', 'child' => $input];
        }

        $this->expectException(\JsonMapper_Exception::class);
        $this->expectExceptionMessage('nesting too deep');
        $this->mapper()->map($input, new PlanNestedDto());
    }

    public function testUnknownKeysAreIgnoredByDefault(): void
    {
        $mapper = $this->mapper();
        $dto = $mapper->map(['name' => 'ok', 'unknown' => 1], new PlanProbeDto());

        $this->assertSame('ok', $dto->name);
        $this->assertSame(1, $mapper->buildCounts[PlanProbeDto::class]);
    }

    public function testUnknownKeysCanThrow(): void
    {
        $mapper = $this->mapper();
        $mapper->bExceptionOnUndefinedProperty = true;

        $this->expectException(\JsonMapper_Exception::class);
        $this->expectExceptionMessage('does not exist');
        $mapper->map(['unknown' => 1], new PlanProbeDto());
    }

    public function testUnknownPropertyHandlerCanMapToDeclaredField(): void
    {
        $mapper = $this->mapper();
        $mapper->undefinedPropertyHandler = static fn ($object, $key, $value) => $key === 'display_name' ? 'name' : null;

        $dto = $mapper->map(['display_name' => 'handled'], new PlanProbeDto());

        $this->assertSame('handled', $dto->name);
    }

    public function testUnknownKeysAreNotLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('log');
        $mapper = $this->mapper();
        $mapper->setLogger($logger);
        $data = [];
        for ($i = 0; $i < 100; ++$i) {
            $data['unknown_' . $i] = $i;
        }

        $mapper->map($data, new PlanProbeDto());
    }

    public function testResolvedInputMapsNestedAliasesAndNullItemsWithSamePlans(): void
    {
        $mapper = $this->mapper();
        $resolved = $mapper->resolveInput([
            'child_data' => ['display_name' => 'child'],
            'item_list' => [['display_name' => 'first'], null],
            'setter_items' => [['display_name' => 'second']],
        ], PlanResolvedParentDto::class);

        $this->assertSame('child', $resolved->data['child']['name']);
        $this->assertSame('first', $resolved->data['items'][0]['name']);
        $this->assertNull($resolved->data['items'][1]);
        $this->assertSame('second', $resolved->data['setterItems'][0]['name']);

        $dto = $mapper->mapResolvedInput($resolved, new PlanResolvedParentDto());
        $this->assertSame('child', $dto->child->name);
        $this->assertSame('first', $dto->items[0]->name);
        $this->assertNull($dto->items[1]);
        $this->assertSame('second', $dto->getSetterItems()[0]->name);
        $this->assertSame(1, $mapper->buildCounts[PlanResolvedParentDto::class]);
        $this->assertSame(1, $mapper->buildCounts[PlanResolvedChildDto::class]);
    }

    public function testResolvedInputUsesClassMappedNestedType(): void
    {
        $mapper = $this->mapper();
        $mapper->classMap[PlanDeclaredChildDto::class] = PlanMappedChildDto::class;
        $resolved = $mapper->resolveInput([
            'child' => ['display_name' => 'mapped'],
        ], PlanClassMapParentDto::class);

        $dto = $mapper->mapResolvedInput($resolved, new PlanClassMapParentDto());
        $this->assertInstanceOf(PlanMappedChildDto::class, $dto->child);
        $this->assertSame('mapped', $dto->child->title);
    }

    public function testResolvedInputUsesClassFactoryNestedType(): void
    {
        $mapper = $this->mapper();
        $mapper->classFactories[PlanFactoryBaseDto::class] = static fn () => new PlanFactoryChildDto();

        $resolved = $mapper->resolveInput([
            'child' => ['display_name' => 'factory'],
        ], PlanFactoryParentDto::class);

        $dto = $mapper->mapResolvedInput($resolved, new PlanFactoryParentDto());
        $this->assertInstanceOf(PlanFactoryChildDto::class, $dto->child);
        $this->assertSame('factory', $dto->child->title);
    }

    public function testResolvedInputUsesClassMapForInterfaceProperty(): void
    {
        $mapper = $this->mapper();
        $mapper->classMap[PlanShapeInterface::class] = PlanCircleDto::class;

        $resolved = $mapper->resolveInput([
            'shape' => ['radius_cm' => 5],
        ], PlanShapeParentDto::class);

        $dto = $mapper->mapResolvedInput($resolved, new PlanShapeParentDto());
        $this->assertInstanceOf(PlanCircleDto::class, $dto->shape);
        $this->assertSame(5, $dto->shape->radiusCm);
    }

    public function testResolvedInputUsesClassMapWithLeadingSlash(): void
    {
        $mapper = $this->mapper();
        $mapper->classMap['\\' . PlanShapeInterface::class] = PlanCircleDto::class;

        $resolved = $mapper->resolveInput([
            'shape' => ['radius_cm' => 5],
        ], PlanShapeParentDto::class);

        $dto = $mapper->mapResolvedInput($resolved, new PlanShapeParentDto());
        $this->assertInstanceOf(PlanCircleDto::class, $dto->shape);
        $this->assertSame(5, $dto->shape->radiusCm);
    }

    public function testResolvedInputUsesPlanTypeForVisibilityOnlyField(): void
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        $mapper->bIgnoreVisibility = true;

        $resolved = $mapper->resolveInput([
            'child' => ['display_name' => 'hidden'],
        ], PlanHiddenParentDto::class);

        $dto = $mapper->mapResolvedInput($resolved, new PlanHiddenParentDto());
        $this->assertSame('hidden', $dto->getChild()?->title);
    }

    public function testTraversableInputIsReadLikeArrayInput(): void
    {
        $mapper = $this->mapper();
        $dto = $mapper->map(new \ArrayObject(['name' => 'traversable']), new PlanProbeDto());

        $this->assertSame('traversable', $dto->name);
        $resolved = $mapper->resolveInput(new \ArrayObject(['user_name' => 'resolved']), PlanAliasDto::class);
        $this->assertSame(['userName' => 'resolved'], $resolved->data);
    }

    public function testResolvedInputSupportsMultiDimensionalClassArrays(): void
    {
        $mapper = $this->mapper();
        $input = ['groups' => [[['display_name' => 'nested']]]];
        $resolved = $mapper->resolveInput($input, PlanMultiArrayDto::class);

        $this->assertSame('nested', $resolved->data['groups'][0][0]['name']);
        $dto = $mapper->mapResolvedInput($resolved, new PlanMultiArrayDto());
        $this->assertSame('nested', $dto->groups[0][0]->name);
        $this->assertSame('nested', $mapper->map($input, new PlanMultiArrayDto())->groups[0][0]->name);
    }

    public function testDynamicClassMapUsesOriginalNestedValueOnlyOnce(): void
    {
        $mapper = $this->mapper();
        $calls = 0;
        $mapper->classMap[PlanDynamicBaseDto::class] = static function ($type, $value) use (&$calls) {
            ++$calls;
            return $value instanceof \stdClass ? PlanDynamicADto::class : PlanDynamicBDto::class;
        };
        $resolved = $mapper->resolveInput([
            'child' => (object) ['display_name' => 'dynamic'],
        ], PlanDynamicParentDto::class);

        $dto = $mapper->mapResolvedInput($resolved, new PlanDynamicParentDto());
        $this->assertInstanceOf(PlanDynamicADto::class, $dto->child);
        $this->assertSame('dynamic', $dto->child->value);
        $this->assertSame(1, $calls);
    }

    public function testArrayTypeAttributeSurvivesArraySetterInMappingPlan(): void
    {
        $mapper = $this->mapper();
        $input = ['items' => [['name' => 'ok']]];

        $dto = $mapper->map($input, new PlanArrayTypeSetterDto());
        $this->assertInstanceOf(PlanArrayTypeItemDto::class, $dto->getItems()[0]);
        $this->assertSame('ok', $dto->getItems()[0]->name);

        $resolved = $mapper->resolveInput($input, PlanArrayTypeSetterDto::class);
        $dto = $mapper->mapResolvedInput($resolved, new PlanArrayTypeSetterDto());
        $this->assertInstanceOf(PlanArrayTypeItemDto::class, $dto->getItems()[0]);
        $this->assertSame('ok', $dto->getItems()[0]->name);
    }

    public function testResolvedInputReusesClassFactorySelection(): void
    {
        $mapper = $this->mapper();
        $calls = 0;
        $mapper->classFactories[PlanDynamicBaseDto::class] = static function () use (&$calls) {
            return ++$calls % 2 === 1 ? new PlanDynamicADto() : new PlanDynamicBDto();
        };

        $resolved = $mapper->resolveInput([
            'child' => ['display_name' => 'factory'],
        ], PlanDynamicParentDto::class);
        $dto = $mapper->mapResolvedInput($resolved, new PlanDynamicParentDto());

        $this->assertSame(1, $calls);
        $this->assertInstanceOf(PlanDynamicADto::class, $dto->child);
        $this->assertSame('factory', $dto->child->value);
    }

    public function testResolvedInputKeepsRawValuesWhenClassMapReturnsNull(): void
    {
        $mapper = $this->mapper();
        $mapper->classMap[PlanDeclaredChildDto::class] = static fn () => null;
        $input = [
            'child' => ['name' => 'child'],
            'items' => [['name' => 'first']],
        ];

        $resolved = $mapper->resolveInput($input, PlanNullClassMapParentDto::class);
        $this->assertSame($input, $resolved->data);

        $dto = $mapper->mapResolvedInput($resolved, new PlanNullClassMapParentDto());
        $this->assertSame($input['child'], $dto->child);
        $this->assertSame($input['items'], $dto->items);
    }

    public function testIgnoreVisibilityMapsHyphenatedPrivateProperty(): void
    {
        $mapper = $this->mapper();
        $mapper->bIgnoreVisibility = true;

        $dto = $mapper->map(['user-name' => 'hidden'], new PlanPrivateHyphenDto());

        $this->assertSame('hidden', $dto->getUserName());
    }

    public function testIgnoreVisibilityIncludesParentPrivateProperties(): void
    {
        $mapper = $this->mapper();
        $mapper->bIgnoreVisibility = true;

        $dto = $mapper->map(['secret' => 'inherited'], new PlanChildPrivateDto());

        $this->assertSame('inherited', $dto->getSecret());
    }

    public function testChangingVisibilityRebuildsAccessorPlans(): void
    {
        $mapper = $this->mapper();
        $mapper->map(['a' => 'first'], new PlanVisibilityDto());
        $mapper->bIgnoreVisibility = true;

        $dto = $mapper->map(['b' => 'second'], new PlanVisibilityDto());

        $this->assertSame('second', $dto->getB());
    }

    public function testUnknownPropertyHandlerReceivesSafeKey(): void
    {
        $mapper = $this->mapper();
        $receivedKey = null;
        $mapper->undefinedPropertyHandler = static function ($object, $key, $value) use (&$receivedKey) {
            $receivedKey = $key;
            return $key === 'DisplayName' ? 'name' : null;
        };

        $dto = $mapper->map(['display-name' => 'handled'], new PlanProbeDto());

        $this->assertSame('handled', $dto->name);
        $this->assertSame('DisplayName', $receivedKey);
    }

    public function testSetterWithoutDeclaredPropertyIsMapped(): void
    {
        $dto = $this->mapper()->map(['name' => 'setter-only'], new PlanSetterOnlyDto());

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
