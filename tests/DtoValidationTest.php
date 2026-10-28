<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\Context\ApplicationContext;
use Hyperf\DTO\Annotation\ArrayType;
use Hyperf\DTO\Annotation\JSONField;
use Hyperf\DTO\Annotation\Validation\Confirmed;
use Hyperf\DTO\Annotation\Validation\Integer;
use Hyperf\DTO\Annotation\Validation\Max;
use Hyperf\DTO\Annotation\Validation\Required;
use Hyperf\DTO\Annotation\Validation\RequiredIf;
use Hyperf\DTO\Annotation\Validation\Validation as CustomValidation;
use Hyperf\DTO\DtoCommon;
use Hyperf\DTO\DtoValidation;
use Hyperf\DTO\Exception\DtoException;
use Hyperf\DTO\Mapper;
use Hyperf\DTO\Scan\PropertyEnum;
use Hyperf\DTO\Scan\PropertyManager;
use Hyperf\DTO\Scan\ValidationManager;
use Hyperf\Validation\ValidationException;
use Mockery as m;
use Psr\Container\ContainerInterface;

/**
 * DtoValidation::validateResolved 全分支测试（含嵌套递归验证）.
 *
 * @internal
 * @coversNothing
 */
class DtoValidationTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        // 复位全局静态开关，避免污染其他用例
        DtoValidation::$isValidationCustomAttributes = false;
        parent::tearDown();
    }

    public function testNonArrayDataThrowsDtoException(): void
    {
        $dtoValidation = $this->makeDtoValidation();

        $this->expectException(DtoException::class);
        $this->expectExceptionMessage('data must be object or array');
        $dtoValidation->validate(ValidationChildDto::class, 'scalar-string');
    }

    public function testClassWithoutRulesStillValidatesNestedObjects(): void
    {
        // B1 已修复: 类自身无 rule 时不再早退，嵌套对象的验证独立生效
        $this->prepareRules(ValidationChildDto::class, 'x');
        $propertyManager = $this->makePropertyManager();
        $propertyManager->scanClassProperties(ValidationNoRuleParentDto::class);

        $dtoValidation = $this->makeDtoValidation($propertyManager);

        // 父类无 rule，嵌套 child.x 为空串违反 required → 递归触发 ValidationException
        $this->expectException(ValidationException::class);
        $dtoValidation->validate(ValidationNoRuleParentDto::class, ['child' => ['x' => '']]);
    }

    public function testFailedRuleThrowsValidationException(): void
    {
        $this->prepareRules(ValidationChildDto::class, 'x');
        $dtoValidation = $this->makeDtoValidation();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The given data was invalid');
        $dtoValidation->validate(ValidationChildDto::class, ['x' => '']);
    }

    public function testValidDataPasses(): void
    {
        $this->prepareRules(ValidationChildDto::class, 'x');
        $dtoValidation = $this->makeDtoValidation();

        $dtoValidation->validate(ValidationChildDto::class, ['x' => 'filled']);
        $this->assertTrue(true);
    }

    public function testAliasRulesApplyToPropertyNameInput(): void
    {
        $this->prepareAliasScalarRules();
        $dtoValidation = $this->makeDtoValidation();

        $dtoValidation->validate(ValidationAliasScalarDto::class, ['userName' => 'ok']);
        $this->expectException(ValidationException::class);
        $dtoValidation->validate(ValidationAliasScalarDto::class, ['userName' => 'TOO-LONG-VALUE']);
    }

    public function testSetterVariantKeysUseDeclaredValidationRules(): void
    {
        $this->prepareSetterVariantRules();
        $dtoValidation = $this->makeDtoValidation();

        foreach (['user_name', 'user-name', ' userName', 'UserName'] as $key) {
            $dtoValidation->validate(ValidationSetterVariantDto::class, [$key => 'ok']);
            try {
                $dtoValidation->validate(ValidationSetterVariantDto::class, [$key => 'TOO-LONG-VALUE']);
                $this->fail('Expected ValidationException was not thrown for key: ' . $key);
            } catch (ValidationException $e) {
                $this->assertStringContainsString('The given data was invalid', $e->getMessage());
            }
        }
    }

    public function testPropertyNameKeyWinsOverEquivalentSetterKey(): void
    {
        $this->prepareSetterVariantRules();
        $dtoValidation = $this->makeDtoValidation();

        // userName 是 PHP 属性名，user_name 是变体；应取 userName 的合法值并通过 Max(5)
        $dtoValidation->validate(ValidationSetterVariantDto::class, [
            'userName' => 'ok',
            'user_name' => 'TOO-LONG-VALUE',
        ]);

        $this->assertTrue(true);
    }

    public function testAcronymSetterVariantUsesDeclaredValidationRules(): void
    {
        (new Integer())->collectProperty(ValidationAcronymSetterDto::class, 'id');
        (new Max(5))->collectProperty(ValidationAcronymSetterDto::class, 'id');
        (new ValidationManager())->generateValidation(ValidationAcronymSetterDto::class, 'id');
        $dtoValidation = $this->makeDtoValidation();

        $dtoValidation->validate(ValidationAcronymSetterDto::class, ['ID' => 3]);
        $this->expectException(ValidationException::class);
        $dtoValidation->validate(ValidationAcronymSetterDto::class, ['ID' => 7]);
    }

    public function testPascalPropertyVariantUsesDeclaredValidationRules(): void
    {
        (new Max(5))->collectProperty(ValidationPascalPropertyDto::class, 'UserName');
        (new ValidationManager())->generateValidation(ValidationPascalPropertyDto::class, 'UserName');
        $dtoValidation = $this->makeDtoValidation();

        $dtoValidation->validate(ValidationPascalPropertyDto::class, ['user_name' => 'TOO-LONG-VALUE']);
        $dtoValidation->validate(ValidationPascalPropertyDto::class, ['user-name' => 'ok']);
        $this->expectException(ValidationException::class);
        $dtoValidation->validate(ValidationPascalPropertyDto::class, ['user-name' => 'TOO-LONG-VALUE']);
    }

    public function testHyphenatedHeaderPropertyUsesDeclaredValidationRules(): void
    {
        (new Max(5))->collectProperty(ValidationHeaderPropertyDto::class, 'xRequestId');
        (new ValidationManager())->generateValidation(ValidationHeaderPropertyDto::class, 'xRequestId');
        $dtoValidation = $this->makeDtoValidation();

        $dtoValidation->validate(ValidationHeaderPropertyDto::class, ['XRequestId' => 'TOO-LONG-VALUE']);
        $dtoValidation->validate(ValidationHeaderPropertyDto::class, ['x-request-id' => 'ok']);
        $this->expectException(ValidationException::class);
        $dtoValidation->validate(ValidationHeaderPropertyDto::class, ['x-request-id' => 'TOO-LONG-VALUE']);
    }

    public function testHyphenatedHeaderAndDeclaredKeyCannotBeSubmittedTogether(): void
    {
        (new Max(5))->collectProperty(ValidationHeaderPropertyDto::class, 'xRequestId');
        (new ValidationManager())->generateValidation(ValidationHeaderPropertyDto::class, 'xRequestId');
        $dtoValidation = $this->makeDtoValidation();

        $this->expectException(ValidationException::class);
        $dtoValidation->validate(ValidationHeaderPropertyDto::class, [
            'x-request-id' => 'ok',
            'xRequestId' => 'TOO-LONG-VALUE',
        ]);
    }

    public function testNestedObjectValidatedRecursively(): void
    {
        $this->prepareRules(ValidationChildDto::class, 'x');
        $this->prepareRules(ValidationParentDto::class, 'name');
        $propertyManager = $this->makePropertyManager();
        $propertyManager->scanClassProperties(ValidationParentDto::class);

        $dtoValidation = $this->makeDtoValidation($propertyManager);

        // 父类 rule 通过（name 非空），嵌套 child.x 为空串 → 递归触发 ValidationException
        $this->expectException(ValidationException::class);
        $dtoValidation->validate(ValidationParentDto::class, ['name' => 'ok', 'child' => ['x' => '']]);
    }

    public function testNestedObjectWithAliasValidatedRecursively(): void
    {
        // B2 已修复: 嵌套递归优先取 JSONField 别名键（与 rule 键一致），
        // 按别名键入的非法嵌套数据会触发 ValidationException
        $this->prepareRules(ValidationChildDto::class, 'x');
        $this->prepareRules(ValidationAliasParentDto::class, 'name');
        (new JSONField('childData'))->collectProperty(ValidationAliasParentDto::class, 'child');

        $propertyManager = $this->makePropertyManager();
        $propertyManager->scanClassProperties(ValidationAliasParentDto::class);

        $dtoValidation = $this->makeDtoValidation($propertyManager);

        $this->expectException(ValidationException::class);
        $dtoValidation->validate(ValidationAliasParentDto::class, ['name' => 'ok', 'childData' => ['x' => '']]);
    }

    public function testNestedObjectWithAliasFallsBackToPropertyNameKey(): void
    {
        // B2 修复的兼容路径: 有别名但数据按 PHP 属性名键入时，回退属性名键仍可验证
        $this->prepareRules(ValidationChildDto::class, 'x');
        $this->prepareRules(ValidationAliasParentDto::class, 'name');
        (new JSONField('childData'))->collectProperty(ValidationAliasParentDto::class, 'child');

        $propertyManager = $this->makePropertyManager();
        $propertyManager->scanClassProperties(ValidationAliasParentDto::class);

        $dtoValidation = $this->makeDtoValidation($propertyManager);

        $this->expectException(ValidationException::class);
        $dtoValidation->validate(ValidationAliasParentDto::class, ['name' => 'ok', 'child' => ['x' => '']]);
    }

    public function testNestedAliasValueWinsOverPropertyName(): void
    {
        $this->prepareRules(ValidationChildDto::class, 'x');
        $this->prepareRules(ValidationAliasParentDto::class, 'name');
        (new JSONField('childData'))->collectProperty(ValidationAliasParentDto::class, 'child');

        $propertyManager = $this->makePropertyManager();
        $propertyManager->scanClassProperties(ValidationAliasParentDto::class);
        $dtoValidation = $this->makeDtoValidation($propertyManager);

        // childData（别名）值合法，child（属性名）值非法；应取别名值并通过校验
        $dtoValidation->validate(ValidationAliasParentDto::class, [
            'name' => 'ok',
            'childData' => ['x' => 'ok'],
            'child' => ['x' => ''],
        ]);

        $this->assertTrue(true);
    }

    public function testNestedClassArrayValidatedPerItem(): void
    {
        $this->prepareRules(ValidationChildDto::class, 'x');
        $this->prepareRules(ValidationArrayParentDto::class, 'name');
        $propertyManager = $this->makePropertyManager();
        $propertyManager->scanClassProperties(ValidationArrayParentDto::class);

        $dtoValidation = $this->makeDtoValidation($propertyManager);

        // 第二项 x 为空串 → 逐项递归时在第二项抛出
        $this->expectException(ValidationException::class);
        $dtoValidation->validate(ValidationArrayParentDto::class, [
            'name' => 'ok',
            'children' => [['x' => 'ok'], ['x' => '']],
        ]);
    }

    public function testEmptyNestedDataStillValidatesRecursively(): void
    {
        $this->prepareRules(ValidationChildDto::class, 'x');
        $this->prepareRules(ValidationParentDto::class, 'name');
        $propertyManager = $this->makePropertyManager();
        $propertyManager->scanClassProperties(ValidationParentDto::class);

        $dtoValidation = $this->makeDtoValidation($propertyManager);
        // child 为空对象时也应执行子类的 required 规则
        $this->expectException(ValidationException::class);
        $dtoValidation->validate(ValidationParentDto::class, ['name' => 'ok', 'child' => []]);
    }

    public function testValidateReturnsImmediatelyWithoutValidatorFactory(): void
    {
        // 容器无 ValidatorFactoryInterface → validate 直接返回，不执行任何验证
        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('has')->withAnyArgs()->andReturn(false);
        ApplicationContext::setContainer($container);

        $this->prepareRules(ValidationChildDto::class, 'x');
        $dtoValidation = new DtoValidation($this->makePropertyManager(), new ValidationManager());

        $dtoValidation->validate(ValidationChildDto::class, ['x' => '']);
        $dtoValidation->validate(ValidationChildDto::class, 'scalar-string');
        $dtoValidation->validate(ValidationChildDto::class, array_fill(0, 1025, 'x'));
        $this->assertTrue(true);
    }

    public function testCustomAttributesPassedToValidatorWhenEnabled(): void
    {
        $this->prepareRules(ValidationAttrDto::class, 'name');

        // 白盒播种 messages/attributes（ValidationManager 无公开 setter）
        $contentProperty = new \ReflectionProperty(ValidationManager::class, 'content');
        $content = $contentProperty->getValue();
        $content[ValidationAttrDto::class]['messages'] = ['name.required' => 'The :attribute is missing'];
        $content[ValidationAttrDto::class]['attributes'] = ['name' => 'User Name'];
        $contentProperty->setValue(null, $content);

        DtoValidation::$isValidationCustomAttributes = true;
        $dtoValidation = $this->makeDtoValidation();

        try {
            $dtoValidation->validate(ValidationAttrDto::class, ['name' => '']);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            // attributes 生效：消息中的 :attribute 被替换为 'User Name'
            $this->assertStringContainsString('The User Name is missing', $e->getMessage());
        }
    }

    public function testCustomAttributesIgnoredWhenDisabled(): void
    {
        $this->prepareRules(ValidationAttrDto::class, 'name');

        $contentProperty = new \ReflectionProperty(ValidationManager::class, 'content');
        $content = $contentProperty->getValue();
        $content[ValidationAttrDto::class]['messages'] = ['name.required' => 'The :attribute is missing'];
        $content[ValidationAttrDto::class]['attributes'] = ['name' => 'User Name'];
        $contentProperty->setValue(null, $content);

        // 开关关闭（默认）：attributes 不传给验证器，:attribute 回退为字段名
        $dtoValidation = $this->makeDtoValidation();

        try {
            $dtoValidation->validate(ValidationAttrDto::class, ['name' => '']);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('The name is missing', $e->getMessage());
        }
    }

    public function testValidationErrorsUseExternalAliasForAliasAndPropertyInput(): void
    {
        $this->prepareAliasScalarRules();
        $dtoValidation = $this->makeDtoValidation();

        foreach (['user_name', 'userName'] as $inputKey) {
            try {
                $dtoValidation->validate(ValidationAliasScalarDto::class, [$inputKey => 'TOO-LONG-VALUE']);
                $this->fail('Expected ValidationException was not thrown.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('user_name', $e->errors());
                $this->assertArrayNotHasKey('userName', $e->errors());
                $this->assertStringContainsString('user_name', $e->getMessage());
            }
        }
    }

    public function testAliasValueWinsOverPropertyNameInput(): void
    {
        $this->prepareAliasScalarRules();
        $dtoValidation = $this->makeDtoValidation();

        // 等价键同时出现时取别名 user_name 的值；若取属性名值会触发 Max(5)
        $dtoValidation->validate(ValidationAliasScalarDto::class, [
            'user_name' => 'ok',
            'userName' => 'TOO-LONG-VALUE',
        ]);

        $this->assertTrue(true);
    }

    public function testNullItemsInNestedClassArrayAreSkipped(): void
    {
        $this->prepareRules(ValidationChildDto::class, 'x');
        $this->prepareRules(ValidationArrayParentDto::class, 'name');
        $propertyManager = $this->makePropertyManager();
        $propertyManager->scanClassProperties(ValidationArrayParentDto::class);
        $dtoValidation = $this->makeDtoValidation($propertyManager);

        $dtoValidation->validate(ValidationArrayParentDto::class, [
            'name' => 'ok',
            'children' => [null, ['x' => 'ok']],
        ]);
        $this->assertTrue(true);
    }

    public function testScalarElementsInSetterDeclaredClassArrayAreSkipped(): void
    {
        $dtoValidation = $this->makeDtoValidation();

        // Status[] 的叶子是标量/枚举值，不应按 DTO 递归验证
        $dtoValidation->validate(ValidationEnumArrayDto::class, [
            'statuses' => ['on', 'off'],
        ]);

        $dto = Mapper::map(['statuses' => ['on', 'off']], new ValidationEnumArrayDto());
        $this->assertSame([ValidationStatusEnum::ON, ValidationStatusEnum::OFF], $dto->getStatuses());

        // #[Valid] 路径：同一个 ResolvedInput 先验证再映射
        $mapper = Mapper::getJsonMapper();
        $resolved = $mapper->resolveInput(['statuses' => ['on', 'off']], ValidationEnumArrayDto::class);
        $dtoValidation->validateResolvedInput($resolved);
        $dto = $mapper->mapResolvedInput($resolved, new ValidationEnumArrayDto());
        $this->assertSame([ValidationStatusEnum::ON, ValidationStatusEnum::OFF], $dto->getStatuses());
    }

    public function testSetterBackedArrayTypeAttributeValidatesChildren(): void
    {
        $this->prepareRules(ValidationChildDto::class, 'x');
        $propertyManager = $this->makePropertyManager();
        $propertyManager->scanClassProperties(ValidationSetterArrayTypeDto::class);
        $dtoValidation = $this->makeDtoValidation($propertyManager);

        $dtoValidation->validate(ValidationSetterArrayTypeDto::class, [
            'items' => [['x' => 'ok']],
        ]);

        $this->expectException(ValidationException::class);
        $dtoValidation->validate(ValidationSetterArrayTypeDto::class, [
            'items' => [['x' => '']],
        ]);
    }

    public function testVariadicClassSetterUsesClassArrayValidationAndMapping(): void
    {
        $this->prepareRules(ValidationVariadicChildDto::class, 'name');
        $propertyManager = $this->makePropertyManager();
        $propertyManager->scanClassProperties(ValidationVariadicParentDto::class);
        $dtoValidation = $this->makeDtoValidation($propertyManager);

        $input = ['children' => [['display_name' => 'ok'], ['display_name' => 'ok2']]];
        $dtoValidation->validate(ValidationVariadicParentDto::class, $input);

        $dto = Mapper::map($input, new ValidationVariadicParentDto());
        $this->assertInstanceOf(ValidationVariadicChildDto::class, $dto->getChildren()[0]);
        $this->assertSame('ok', $dto->getChildren()[0]->name);

        // #[Valid] 路径：同一个 ResolvedInput 先验证再映射
        $mapper = Mapper::getJsonMapper();
        $resolved = $mapper->resolveInput($input, ValidationVariadicParentDto::class);
        $dtoValidation->validateResolvedInput($resolved);
        $dto = $mapper->mapResolvedInput($resolved, new ValidationVariadicParentDto());
        $this->assertInstanceOf(ValidationVariadicChildDto::class, $dto->getChildren()[0]);
        $this->assertSame('ok', $dto->getChildren()[0]->name);

        $this->expectException(ValidationException::class);
        $dtoValidation->validate(ValidationVariadicParentDto::class, [
            'children' => [['display_name' => '']],
        ]);
    }

    public function testNonArrayValueForClassArrayIsLeftToParentRules(): void
    {
        $this->prepareRules(ValidationChildDto::class, 'x');
        $this->prepareRules(ValidationArrayParentDto::class, 'name');
        $propertyManager = $this->makePropertyManager();
        $propertyManager->scanClassProperties(ValidationArrayParentDto::class);
        $dtoValidation = $this->makeDtoValidation($propertyManager);

        // children 声明为 Child[]，标量输入交给父级规则，不抛未捕获 DtoException
        $dtoValidation->validate(ValidationArrayParentDto::class, [
            'name' => 'ok',
            'children' => 'not-an-array',
        ]);

        $this->assertTrue(true);
    }

    public function testMultiDimensionalClassArrayValidatesLeafItems(): void
    {
        $this->prepareRules(ValidationChildDto::class, 'x');
        $propertyManager = $this->makePropertyManager();
        $propertyManager->scanClassProperties(ValidationMultiArrayParentDto::class);
        $dtoValidation = $this->makeDtoValidation($propertyManager);

        $dtoValidation->validate(ValidationMultiArrayParentDto::class, [
            'groups' => [[['x' => 'ok'], null]],
        ]);
        $this->expectException(ValidationException::class);
        $dtoValidation->validate(ValidationMultiArrayParentDto::class, [
            'groups' => [[['x' => '']]],
        ]);
    }

    public function testCustomKeyErrorsUseExternalAliasAndKeepArrayIndex(): void
    {
        (new JSONField('user_ids'))->collectProperty(ValidationCustomKeyAliasDto::class, 'ids');
        (new CustomValidation('integer', 'The :attribute must be an integer', 'ids.*'))->collectProperty(ValidationCustomKeyAliasDto::class, 'ids');
        (new ValidationManager())->generateValidation(ValidationCustomKeyAliasDto::class, 'ids');
        $dtoValidation = $this->makeDtoValidation();

        try {
            $dtoValidation->validate(ValidationCustomKeyAliasDto::class, ['user_ids' => [1, 'invalid']]);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('user_ids.1', $e->errors());
            $this->assertStringContainsString('user_ids', $e->getMessage());
        }
    }

    public function testCrossFieldRuleUsesCanonicalPropertyReference(): void
    {
        (new JSONField('contact_type'))->collectProperty(ValidationCrossFieldDto::class, 'type');
        (new JSONField('contact_value'))->collectProperty(ValidationCrossFieldDto::class, 'value');
        (new RequiredIf('type', 'email', 'The :attribute is required when :other is :value'))->collectProperty(ValidationCrossFieldDto::class, 'value');
        (new ValidationManager())->generateValidation(ValidationCrossFieldDto::class, 'value');
        $dtoValidation = $this->makeDtoValidation();

        $dtoValidation->validate(ValidationCrossFieldDto::class, ['contact_type' => 'phone']);
        try {
            $dtoValidation->validate(ValidationCrossFieldDto::class, [
                'contact_type' => 'email',
                'contact_value' => '',
            ]);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('contact_value', $e->errors());
            $this->assertStringContainsString('contact_type', $e->getMessage());
        }
    }

    public function testInheritedPrivateNestedPropertyIsNotValidated(): void
    {
        $this->prepareRules(ValidationChildDto::class, 'x');
        $propertyManager = $this->makePropertyManager();
        $propertyManager->scanClassProperties(ValidationInheritedPrivateChildDto::class);
        $dtoValidation = $this->makeDtoValidation($propertyManager);

        $dtoValidation->validate(ValidationInheritedPrivateChildDto::class, ['secret' => 'not-an-object']);
        $dtoValidation->validate(ValidationInheritedPrivateChildDto::class, ['secret' => ['x' => '']]);

        $this->assertTrue(true);
    }

    public function testValidationAttributeTranslationsOverrideAliasDefaults(): void
    {
        $this->prepareAliasScalarRules();

        foreach ([['user_name', 'Wire Name'], ['userName', 'Property Name']] as [$translationKey, $displayName]) {
            $dtoValidation = $this->makeDtoValidation(null, [
                'validation' => ['attributes' => [$translationKey => $displayName]],
            ]);

            try {
                $dtoValidation->validate(ValidationAliasScalarDto::class, ['user_name' => 'TOO-LONG-VALUE']);
                $this->fail('Expected ValidationException was not thrown.');
            } catch (ValidationException $e) {
                $this->assertSame("The {$displayName} is too long", $e->errors()['user_name'][0]);
            }
        }
    }

    public function testExternalizedErrorsPreserveValidatorFailedRules(): void
    {
        $this->prepareAliasScalarRules();
        $dtoValidation = $this->makeDtoValidation();

        try {
            $dtoValidation->validate(ValidationAliasScalarDto::class, ['user_name' => 'TOO-LONG-VALUE']);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('user_name', $e->errors());
            $this->assertArrayHasKey('userName', $e->validator->failed());
        }
    }

    public function testBroadSetterTypesKeepDeclaredNestedMetadata(): void
    {
        $this->prepareRules(ValidationChildDto::class, 'x');
        $propertyManager = $this->makePropertyManager();
        $propertyManager->scanClassProperties(ValidationBroadSetterDto::class);
        $dtoValidation = $this->makeDtoValidation($propertyManager);

        foreach (['items', 'groups', 'child'] as $field) {
            $value = $field === 'child' ? ['x' => ''] : [['x' => '']];
            try {
                $dtoValidation->validate(ValidationBroadSetterDto::class, [$field => $value]);
                $this->fail("Expected nested validation for {$field} was not triggered.");
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }

        $input = [
            'items' => [['x' => 'ok']],
            'groups' => [['x' => 'ok']],
            'child' => ['x' => 'ok'],
        ];
        $resolved = $dtoValidation->prepare(ValidationBroadSetterDto::class, $input);
        $dtoValidation->validateResolvedInput($resolved);
        $dto = Mapper::mapResolvedInput($resolved, new ValidationBroadSetterDto());
        $this->assertInstanceOf(ValidationChildDto::class, $dto->getItems()[0]);
        $this->assertInstanceOf(ValidationChildDto::class, $dto->getGroups()[0]);
        $this->assertInstanceOf(ValidationChildDto::class, $dto->child);
    }

    public function testConfirmedUsesCanonicalAndAliasConfirmationFields(): void
    {
        (new JSONField('password'))->collectProperty(ValidationConfirmedDto::class, 'pwd');
        (new Confirmed())->collectProperty(ValidationConfirmedDto::class, 'pwd');
        (new ValidationManager())->generateValidation(ValidationConfirmedDto::class, 'pwd');
        $dtoValidation = $this->makeDtoValidation();

        $dtoValidation->validate(ValidationConfirmedDto::class, [
            'password' => 'secret',
            'password_confirmation' => 'secret',
        ]);
        $dtoValidation->validate(ValidationConfirmedDto::class, [
            'pwd' => 'secret',
            'pwd_confirmation' => 'secret',
        ]);
        $this->assertTrue(true);

        try {
            $dtoValidation->validate(ValidationConfirmedDto::class, [
                'password' => 'secret',
                'password_confirmation' => 'different',
            ]);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('password', $e->errors());
        }
    }

    public function testLegacyAliasCustomKeyIsCanonicalized(): void
    {
        (new JSONField('user_ids'))->collectProperty(ValidationCustomKeyAliasDto::class, 'ids');
        (new CustomValidation('integer', 'Legacy :attribute rule', 'user_ids.*'))->collectProperty(ValidationCustomKeyAliasDto::class, 'ids');
        (new ValidationManager())->generateValidation(ValidationCustomKeyAliasDto::class, 'ids');
        $dtoValidation = $this->makeDtoValidation();

        try {
            $dtoValidation->validate(ValidationCustomKeyAliasDto::class, ['user_ids' => ['a', 'b']]);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('user_ids.0', $e->errors());
            $this->assertArrayHasKey('user_ids.1', $e->errors());
            $this->assertStringContainsString('Legacy user_ids.* rule', $e->getMessage());
        }
    }

    // ---------------- 工具方法 ----------------

    private function prepareAliasScalarRules(): void
    {
        (new JSONField('user_name'))->collectProperty(ValidationAliasScalarDto::class, 'userName');
        (new Max(5, 'The :attribute is too long'))->collectProperty(ValidationAliasScalarDto::class, 'userName');
        (new ValidationManager())->generateValidation(ValidationAliasScalarDto::class, 'userName');
    }

    private function prepareSetterVariantRules(): void
    {
        (new Max(5))->collectProperty(ValidationSetterVariantDto::class, 'userName');
        (new ValidationManager())->generateValidation(ValidationSetterVariantDto::class, 'userName');
    }

    /**
     * 为类的指定属性播种 Required 规则并生成验证数据.
     */
    private function prepareRules(string $className, string $fieldName): void
    {
        (new Required())->collectProperty($className, $fieldName);
        (new ValidationManager())->generateValidation($className, $fieldName);
    }

    private function makePropertyManager(): PropertyManager
    {
        return new PropertyManager(new DtoCommon(), new PropertyEnum());
    }

    private function makeDtoValidation(?PropertyManager $propertyManager = null, array $translationGroups = []): DtoValidation
    {
        $this->setContainerWithValidatorFactory($translationGroups);
        return new DtoValidation($propertyManager ?? $this->makePropertyManager(), new ValidationManager());
    }
}

class ValidationChildDto
{
    public string $x = '';
}

class ValidationParentDto
{
    public string $name = '';

    public ?ValidationChildDto $child = null;
}

class ValidationNoRuleParentDto
{
    public ?ValidationChildDto $child = null;
}

class ValidationAliasParentDto
{
    public string $name = '';

    public ?ValidationChildDto $child = null;
}

class ValidationAliasScalarDto
{
    public string $userName = '';
}

enum ValidationStatusEnum: string
{
    case ON = 'on';
    case OFF = 'off';
}

class ValidationEnumArrayDto
{
    private array $statuses = [];

    /** @param ValidationStatusEnum[] $statuses */
    public function setStatuses(array $statuses): void
    {
        $this->statuses = $statuses;
    }

    public function getStatuses(): array
    {
        return $this->statuses;
    }
}

class ValidationSetterArrayTypeDto
{
    #[ArrayType(ValidationChildDto::class)]
    private array $items = [];

    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function getItems(): array
    {
        return $this->items;
    }
}

class ValidationVariadicChildDto
{
    #[JSONField('display_name')]
    public string $name = '';
}

class ValidationVariadicParentDto
{
    private array $children = [];

    public function setChildren(ValidationVariadicChildDto ...$children): void
    {
        $this->children = $children;
    }

    public function getChildren(): array
    {
        return $this->children;
    }
}

class ValidationSetterVariantDto
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

class ValidationAcronymSetterDto
{
    private int $id = 0;

    public function setID(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }
}

class ValidationPascalPropertyDto
{
    public string $UserName = '';
}

class ValidationHeaderPropertyDto
{
    public string $xRequestId = '';
}

class ValidationArrayParentDto
{
    public string $name = '';

    /**
     * @var ValidationChildDto[]
     */
    public array $children = [];
}

class ValidationAttrDto
{
    public string $name = '';
}

class ValidationCustomKeyAliasDto
{
    public array $ids = [];
}

class ValidationMultiArrayParentDto
{
    /** @var ValidationChildDto[][] */
    public array $groups = [];
}

class ValidationCrossFieldDto
{
    public string $type = '';

    public string $value = '';
}

class ValidationInheritedPrivateParentDto
{
    private ?ValidationChildDto $secret = null;
}

class ValidationInheritedPrivateChildDto extends ValidationInheritedPrivateParentDto
{
}

class ValidationBroadSetterDto
{
    #[ArrayType(ValidationChildDto::class)]
    private array $items = [];

    /** @var ValidationChildDto[] */
    private array $groups = [];

    public ?ValidationChildDto $child = null;

    public function setItems(mixed $items): void
    {
        $this->items = $items;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function setGroups(iterable $groups): void
    {
        $this->groups = $groups;
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function setChild(object $child): void
    {
        $this->child = $child;
    }
}

class ValidationConfirmedDto
{
    public string $pwd = '';
}
