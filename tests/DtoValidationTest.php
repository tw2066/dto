<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\Context\ApplicationContext;
use Hyperf\DTO\Annotation\JSONField;
use Hyperf\DTO\Annotation\Validation\Integer;
use Hyperf\DTO\Annotation\Validation\Max;
use Hyperf\DTO\Annotation\Validation\Required;
use Hyperf\DTO\DtoCommon;
use Hyperf\DTO\DtoValidation;
use Hyperf\DTO\Exception\DtoException;
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

    public function testAliasAndPropertyNameCannotBeSubmittedTogether(): void
    {
        $this->prepareAliasScalarRules();
        $dtoValidation = $this->makeDtoValidation();

        $this->expectException(ValidationException::class);
        $dtoValidation->validate(ValidationAliasScalarDto::class, [
            'user_name' => 'ok',
            'userName' => 'TOO-LONG-VALUE',
        ]);
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

    public function testEquivalentSetterKeysCannotBeSubmittedTogether(): void
    {
        $this->prepareSetterVariantRules();
        $dtoValidation = $this->makeDtoValidation();

        $this->expectException(ValidationException::class);
        $dtoValidation->validate(ValidationSetterVariantDto::class, [
            'userName' => 'ok',
            'user_name' => 'TOO-LONG-VALUE',
        ]);
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

    public function testNestedAliasAndPropertyNameCannotBeSubmittedTogether(): void
    {
        $this->prepareRules(ValidationChildDto::class, 'x');
        $this->prepareRules(ValidationAliasParentDto::class, 'name');
        (new JSONField('childData'))->collectProperty(ValidationAliasParentDto::class, 'child');

        $propertyManager = $this->makePropertyManager();
        $propertyManager->scanClassProperties(ValidationAliasParentDto::class);
        $dtoValidation = $this->makeDtoValidation($propertyManager);

        $this->expectException(ValidationException::class);
        $dtoValidation->validate(ValidationAliasParentDto::class, [
            'name' => 'ok',
            'childData' => ['x' => 'ok'],
            'child' => ['x' => ''],
        ]);
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

    public function testEmptyNestedDataSkipsRecursion(): void
    {
        $this->prepareRules(ValidationChildDto::class, 'x');
        $this->prepareRules(ValidationParentDto::class, 'name');
        $propertyManager = $this->makePropertyManager();
        $propertyManager->scanClassProperties(ValidationParentDto::class);

        $dtoValidation = $this->makeDtoValidation($propertyManager);
        // child 为空数组 → empty() 分支跳过递归，不抛异常
        $dtoValidation->validate(ValidationParentDto::class, ['name' => 'ok', 'child' => []]);
        $this->assertTrue(true);
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

    // ---------------- 工具方法 ----------------

    private function prepareAliasScalarRules(): void
    {
        (new JSONField('user_name'))->collectProperty(ValidationAliasScalarDto::class, 'userName');
        (new Max(5))->collectProperty(ValidationAliasScalarDto::class, 'userName');
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

    private function makeDtoValidation(?PropertyManager $propertyManager = null): DtoValidation
    {
        $this->setContainerWithValidatorFactory();
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
