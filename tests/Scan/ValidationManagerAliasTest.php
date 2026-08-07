<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Scan;

use Hyperf\ApiDocs\Annotation\ApiModelProperty;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\DTO\Annotation\JSONField;
use Hyperf\DTO\Annotation\Validation\Exists;
use Hyperf\DTO\Annotation\Validation\Required;
use Hyperf\DTO\Annotation\Validation\Validation;
use Hyperf\DTO\Scan\ValidationManager;
use Hyperf\Validation\Rules\Exists as ExistsRule;
use HyperfTest\DTO\AbstractTestCase;

/**
 * ValidationManager::generateValidation 规则生成测试（别名键 / customKey / messages 对齐 / attributes / 对象 rule）.
 *
 * @internal
 * @coversNothing
 */
class ValidationManagerAliasTest extends AbstractTestCase
{
    public function testAliasUsedAsRuleAndMessageKey(): void
    {
        (new JSONField('userName'))->collectProperty(VmAliasDto::class, 'name');
        (new Required('名字必填'))->collectProperty(VmAliasDto::class, 'name');

        $manager = new ValidationManager();
        $manager->generateValidation(VmAliasDto::class, 'name');

        $data = $manager->getData(VmAliasDto::class);
        // rule 键与 messages 键都用 JSONField 别名而非 PHP 属性名
        $this->assertSame(['required'], $data['rule']['userName']);
        $this->assertSame('名字必填', $data['messages']['userName.required']);
        $this->assertArrayNotHasKey('name', $data['rule']);
    }

    public function testAliasWithoutValidationAnnotationsReturnsEmpty(): void
    {
        (new JSONField('userName'))->collectProperty(VmAliasOnlyDto::class, 'name');

        $manager = new ValidationManager();
        $manager->generateValidation(VmAliasOnlyDto::class, 'name');

        $this->assertSame([], $manager->getData(VmAliasOnlyDto::class));
    }

    public function testCustomKeyUsedAsRuleKey(): void
    {
        (new Validation('integer', '', 'intArr.*'))->collectProperty(VmCustomKeyDto::class, 'intArr');

        $manager = new ValidationManager();
        $manager->generateValidation(VmCustomKeyDto::class, 'intArr');

        $data = $manager->getData(VmCustomKeyDto::class);
        $this->assertSame(['integer'], $data['rule']['intArr.*']);
    }

    public function testCustomKeyPollutesFollowingAnnotations(): void
    {
        // B3 回归（疑似 bug，按当前行为锁定）:
        // generateValidation 循环内 $fieldName 被第一个注解的 customKey 重赋值，
        // 同一属性的后续注解 setFieldName 拿到的是污染后的值。
        // 当前行为: 'string' 规则也挂到 'items.*' 键下，'value' 键消失。
        // 若未来修复（改用局部变量），本用例会失败，届时翻转为期待 rule['value'] = ['string']。
        (new Validation('integer', '', 'items.*'))->collectProperty(VmPollutionDto::class, 'value');
        (new Validation('string'))->collectProperty(VmPollutionDto::class, 'value');

        $manager = new ValidationManager();
        $manager->generateValidation(VmPollutionDto::class, 'value');

        $data = $manager->getData(VmPollutionDto::class);
        $this->assertSame(['integer', 'string'], $data['rule']['items.*']);
        $this->assertArrayNotHasKey('value', $data['rule']);
    }

    public function testMessageKeyUsesRuleNameBeforeColon(): void
    {
        // 带参数的规则 max:5，message 键取冒号前段 'max'
        (new Validation('max:5', 'too big'))->collectProperty(VmColonMessageDto::class, 'value');

        $manager = new ValidationManager();
        $manager->generateValidation(VmColonMessageDto::class, 'value');

        $data = $manager->getData(VmColonMessageDto::class);
        $this->assertSame(['max:5'], $data['rule']['value']);
        $this->assertSame('too big', $data['messages']['value.max']);
    }

    public function testMessagesAlignedByPositionWhenShorterThanRules(): void
    {
        // messages 数量少于规则数量时按位置对齐，缺少的规则无自定义消息
        (new Validation(['string', 'min:5'], ['only-string']))->collectProperty(VmShortMessageDto::class, 'value');

        $manager = new ValidationManager();
        $manager->generateValidation(VmShortMessageDto::class, 'value');

        $data = $manager->getData(VmShortMessageDto::class);
        $this->assertSame(['string', 'min:5'], $data['rule']['value']);
        $this->assertSame('only-string', $data['messages']['value.string']);
        $this->assertArrayNotHasKey('value.min', $data['messages']);
    }

    public function testApiModelPropertyValueWrittenToAttributes(): void
    {
        $this->defineApiModelPropertyStub();

        (new Required())->collectProperty(VmAttributesDto::class, 'name');
        $apiModelProperty = new ApiModelProperty('用户姓名');
        AnnotationCollector::collectProperty(VmAttributesDto::class, 'name', ApiModelProperty::class, $apiModelProperty);

        $manager = new ValidationManager();
        $manager->generateValidation(VmAttributesDto::class, 'name');

        $data = $manager->getData(VmAttributesDto::class);
        $this->assertSame('用户姓名', $data['attributes']['name']);
    }

    public function testApiModelPropertyWithoutRulesWritesNoAttributes(): void
    {
        $this->defineApiModelPropertyStub();

        // 只有 ApiModelProperty 没有验证注解 → 早退，attributes 不写入
        $apiModelProperty = new ApiModelProperty('用户姓名');
        AnnotationCollector::collectProperty(VmAttributesOnlyDto::class, 'name', ApiModelProperty::class, $apiModelProperty);

        $manager = new ValidationManager();
        $manager->generateValidation(VmAttributesOnlyDto::class, 'name');

        $this->assertSame([], $manager->getData(VmAttributesOnlyDto::class));
    }

    public function testObjectRulePassesThroughUnchanged(): void
    {
        // Exists 等对象 rule（Stringable）不经字符串拆分，原样进入 rule 数组
        (new Exists('users', 'email'))->collectProperty(VmObjectRuleDto::class, 'email');

        $manager = new ValidationManager();
        $manager->generateValidation(VmObjectRuleDto::class, 'email');

        $data = $manager->getData(VmObjectRuleDto::class);
        $this->assertCount(1, $data['rule']['email']);
        $rule = $data['rule']['email'][0];
        $this->assertInstanceOf(ExistsRule::class, $rule);
        $this->assertStringContainsString('exists:users,email', (string) $rule);
    }

    /**
     * tangwei/apidocs 未安装，eval 定义 ApiModelProperty stub 以覆盖 attributes 分支.
     */
    private function defineApiModelPropertyStub(): void
    {
        if (! class_exists(ApiModelProperty::class)) {
            eval('namespace Hyperf\ApiDocs\Annotation; class ApiModelProperty { public $value; public function __construct($value = "") { $this->value = $value; } }');
        }
    }
}

class VmAliasDto
{
    public string $name = '';
}

class VmAliasOnlyDto
{
    public string $name = '';
}

class VmCustomKeyDto
{
    public array $intArr = [];
}

class VmPollutionDto
{
    public string $value = '';
}

class VmColonMessageDto
{
    public string $value = '';
}

class VmShortMessageDto
{
    public string $value = '';
}

class VmAttributesDto
{
    public string $name = '';
}

class VmAttributesOnlyDto
{
    public string $name = '';
}

class VmObjectRuleDto
{
    public string $email = '';
}
