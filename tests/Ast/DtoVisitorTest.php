<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Ast;

use Hyperf\DTO\Ast\DtoVisitor;
use Hyperf\DTO\Ast\PropertyInfo;
use HyperfTest\DTO\AbstractTestCase;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

/**
 * DtoVisitor AST 变换测试（纯字符串解析，零 IO）.
 *
 * @internal
 * @coversNothing
 */
class DtoVisitorTest extends AbstractTestCase
{
    private const CODE = <<<'PHP'
<?php

namespace AstDemo;

class Demo
{
    public int $a;
    public string $b;
    public array $c;
    public bool $d;
    public ?int $e;
    public mixed $m;
    public \AstDemo\Address $addr;
}
PHP;

    public function testLevelZeroRecordsButDoesNotInjectDefaults(): void
    {
        [$output, $visitor] = $this->traverse(self::CODE, $this->props('a', 'b', 'c', 'd', 'e', 'm', 'addr'), false, 0);

        // level 0: 不注入默认值
        $this->assertStringContainsString('public int $a;', $output);
        $this->assertStringNotContainsString('$a = 0', $output);
        // 但记录 jsonSerialize 兜底所需的类型信息
        $this->assertSame('int', $visitor->jsonSerializeNotDefaultValue['a']);
        $this->assertSame('string', $visitor->jsonSerializeNotDefaultValue['b']);
        $this->assertSame('array', $visitor->jsonSerializeNotDefaultValue['c']);
        $this->assertSame('bool', $visitor->jsonSerializeNotDefaultValue['d']);
        // 可空与类类型记录为 null
        $this->assertArrayHasKey('e', $visitor->jsonSerializeNotDefaultValue);
        $this->assertNull($visitor->jsonSerializeNotDefaultValue['e']);
        $this->assertArrayHasKey('addr', $visitor->jsonSerializeNotDefaultValue);
        $this->assertNull($visitor->jsonSerializeNotDefaultValue['addr']);
    }

    public function testLevelOneInjectsSimpleTypeDefaults(): void
    {
        [$output] = $this->traverse(self::CODE, $this->props('a', 'b', 'c', 'd', 'e', 'm', 'addr'), false, 1);

        $this->assertStringContainsString('public int $a = 0;', $output);
        $this->assertStringContainsString("public string \$b = '';", $output);
        $this->assertStringContainsString('public array $c = [];', $output);
        $this->assertStringContainsString('public bool $d = false;', $output);
        $this->assertStringContainsString('public ?int $e = null;', $output);
        $this->assertStringContainsString('public mixed $m = null;', $output);
        // 类类型属性 level 1 不注入
        $this->assertStringContainsString('public \AstDemo\Address $addr;', $output);
    }

    public function testLevelTwoRewritesClassTypePropertyToNullable(): void
    {
        [$output] = $this->traverse(self::CODE, $this->props('a', 'b', 'c', 'd', 'e', 'm', 'addr'), false, 2);

        // level 2: 类类型属性被改写为可空并置 null（改变类签名）
        $this->assertStringContainsString('public ?\AstDemo\Address $addr = null;', $output);
    }

    public function testExistingDefaultValueIsUntouched(): void
    {
        $code = <<<'PHP'
<?php

namespace AstDemo;

class Demo
{
    public int $a = 5;
}
PHP;
        [$output] = $this->traverse($code, $this->props('a'), false, 1);

        $this->assertStringContainsString('public int $a = 5;', $output);
    }

    public function testAliasOnlyAffectsJsonSerialize(): void
    {
        $code = <<<'PHP'
<?php

namespace AstDemo;

class Demo
{
    public string $name;
}
PHP;
        $propertyInfo = new PropertyInfo();
        $propertyInfo->propertyName = 'name';
        $propertyInfo->alias = 'user_name';
        $propertyInfo->jsonArrKey = 'user_name';

        [$output] = $this->traverse($code, ['name' => $propertyInfo], true, 0);

        $this->assertStringNotContainsString('_set_dto_alias_', $output);
        $this->assertStringContainsString("'user_name' => \$this->name", $output);
        $this->assertStringContainsString('function jsonSerialize(): array', $output);
    }

    public function testJsonSerializeUsesCoalesceFallbackAndGetter(): void
    {
        $code = <<<'PHP'
<?php

namespace AstDemo;

class Demo
{
    public int $a;
    public string $name;
}
PHP;
        $a = new PropertyInfo();
        $a->propertyName = 'a';
        $a->jsonArrKey = 'a';
        $name = new PropertyInfo();
        $name->propertyName = 'name';
        $name->getMethodName = 'getName';
        $name->jsonArrKey = 'user_name';

        [$output] = $this->traverse($code, ['a' => $a, 'name' => $name], true, 0);

        // 无 getter 属性: level 0 记录 int → $this->a ?? 0 兜底
        $this->assertStringContainsString("'a' => \$this->a ?? 0", $output);
        // 有 getter 属性: 直接调用 getter，key 为转换后的字段名
        $this->assertStringContainsString("'user_name' => \$this->getName()", $output);
        $this->assertStringContainsString('function jsonSerialize(): array', $output);
        $this->assertStringContainsString('implements \JsonSerializable', $output);
    }

    public function testJsonSerializeExcludesNonSerializableProperties(): void
    {
        $code = <<<'PHP'
<?php

namespace AstDemo;

class Demo
{
    public int $a;
    public int $hidden;
}
PHP;
        $a = new PropertyInfo();
        $a->propertyName = 'a';
        $a->jsonArrKey = 'a';
        $hidden = new PropertyInfo();
        $hidden->propertyName = 'hidden';
        $hidden->jsonArrKey = 'hidden';
        $hidden->isJsonSerialize = false;

        [$output] = $this->traverse($code, ['a' => $a, 'hidden' => $hidden], true, 0);

        $this->assertStringContainsString("'a' =>", $output);
        $this->assertStringNotContainsString("'hidden' =>", $output);
    }

    public function testJsonSerializeNotGeneratedWhenAllExcluded(): void
    {
        $code = <<<'PHP'
<?php

namespace AstDemo;

class Demo
{
    public int $hidden;
}
PHP;
        $hidden = new PropertyInfo();
        $hidden->propertyName = 'hidden';
        $hidden->jsonArrKey = 'hidden';
        $hidden->isJsonSerialize = false;

        [$output] = $this->traverse($code, ['hidden' => $hidden], true, 0);

        // createJsonSerialize 返回 null: 不生成方法也不实现接口
        $this->assertStringNotContainsString('jsonSerialize', $output);
        $this->assertStringNotContainsString('JsonSerializable', $output);
    }

    public function testOnlyTargetClassIsModifiedWhenFileContainsMultipleClasses(): void
    {
        $code = <<<'PHP'
<?php

namespace AstDemo;

class Demo
{
    public int $value;
}

class Helper
{
    public int $value;

    public function jsonSerialize(): array
    {
        return [];
    }
}
PHP;
        $propertyInfo = new PropertyInfo();
        $propertyInfo->propertyName = 'value';
        $propertyInfo->alias = 'user_value';
        $propertyInfo->jsonArrKey = 'user_value';

        [$output] = $this->traverse($code, ['value' => $propertyInfo], true, 1, 'AstDemo\\Demo');
        [$demoCode, $helperCode] = explode('class Helper', $output, 2);
        $helperCode = 'class Helper' . $helperCode;

        $this->assertStringContainsString('public int $value = 0;', $demoCode);
        $this->assertStringContainsString('function jsonSerialize(): array', $demoCode);
        $this->assertStringContainsString('public int $value;', $helperCode);
        $this->assertStringNotContainsString('$value = 0', $helperCode);
        $this->assertStringNotContainsString('implements \\JsonSerializable', $helperCode);
        $this->assertSame(1, substr_count($helperCode, 'function jsonSerialize('));
    }

    public function testClassWithoutNamespaceIsSkipped(): void
    {
        // src 现状: afterTraverse 只处理 Namespace_ 节点内的 Class_，

        // 无命名空间的类被静默跳过（此用例锁定该行为）
        $code = <<<'PHP'
<?php

class Demo
{
    public string $name;
}
PHP;
        $propertyInfo = new PropertyInfo();
        $propertyInfo->propertyName = 'name';
        $propertyInfo->alias = 'user_name';
        $propertyInfo->jsonArrKey = 'user_name';

        [$output] = $this->traverse($code, ['name' => $propertyInfo], true, 0);

        $this->assertStringNotContainsString('_set_dto_alias_', $output);
        $this->assertStringNotContainsString('jsonSerialize', $output);
    }

    public function testMultiPropertyDeclarationHandlesEveryProperty(): void
    {
        $code = <<<'PHP'
<?php

namespace AstDemo;

class Demo
{
    public int $a, $b;
}
PHP;
        [$output] = $this->traverse($code, $this->props('a', 'b'), false, 1);

        $this->assertStringContainsString('public int $a = 0, $b = 0;', $output);
    }

    public function testReadonlyPropertiesDoNotReceiveDefaultsOrTypeRewrites(): void
    {
        $code = <<<'PHP'
<?php

namespace AstDemo;

class Demo
{
    public readonly int $id;
    public readonly \AstDemo\Address $addr;
}
PHP;
        [$output] = $this->traverse($code, $this->props('id', 'addr'), false, 2);

        $this->assertStringContainsString('public readonly int $id;', $output);
        $this->assertStringContainsString('public readonly \AstDemo\Address $addr;', $output);
        $this->assertStringNotContainsString('$id = 0', $output);
        $this->assertStringNotContainsString('$addr = null', $output);
    }

    public function testPromotedPropertiesReceiveDefaults(): void
    {
        $code = <<<'PHP'
<?php

namespace AstDemo;

class Demo
{
    public function __construct(int $count, public int $id, private string $name, public \AstDemo\Address $addr)
    {
    }
}
PHP;
        [$output] = $this->traverse($code, $this->props('id', 'name', 'addr'), false, 2);

        $this->assertStringContainsString('int $count,', $output);
        $this->assertStringContainsString('public int $id = 0', $output);
        $this->assertStringContainsString("private string \$name = ''", $output);
        $this->assertStringContainsString('public ?\AstDemo\Address $addr = null', $output);
        $this->assertStringNotContainsString('int $count = 0', $output);
    }

    public function testNormalizeValueWithMixedKeys(): void
    {
        $visitor = new DtoVisitor('Demo', [], false, 0);
        // shortArraySyntax：php-parser 4 与 5 均输出 []
        $printer = new Standard(['shortArraySyntax' => true]);

        // 连续数字键不生成键，关联键生成键
        $expr = $visitor->normalizeValue([1, 2, 'k' => 'v']);
        $this->assertSame("[1, 2, 'k' => 'v']", $printer->prettyPrintExpr($expr));

        $this->assertSame('null', $printer->prettyPrintExpr($visitor->normalizeValue(null)));
        $this->assertSame('true', $printer->prettyPrintExpr($visitor->normalizeValue(true)));
        $this->assertSame('1.5', $printer->prettyPrintExpr($visitor->normalizeValue(1.5)));
        $this->assertSame("'str'", $printer->prettyPrintExpr($visitor->normalizeValue('str')));
    }

    /**
     * @return array<string, PropertyInfo>
     */
    private function props(string ...$names): array
    {
        $arr = [];
        foreach ($names as $name) {
            $propertyInfo = new PropertyInfo();
            $propertyInfo->propertyName = $name;
            $propertyInfo->jsonArrKey = $name;
            $arr[$name] = $propertyInfo;
        }
        return $arr;
    }

    /**
     * @return array{0: string, 1: DtoVisitor}
     */
    private function traverse(string $code, array $propertyArr, bool $isCreateJsonSerialize, int $level, string $classname = 'Demo'): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);
        $visitor = new DtoVisitor($classname, $propertyArr, $isCreateJsonSerialize, $level);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $ast = $traverser->traverse($ast);
        // php-parser 4 与 5 的 pretty printer 输出存在格式差异：
        // shortArraySyntax 统一数组为 []；`) : void`（4.x）归一化为 `): void`（5.x）
        $output = (new Standard(['shortArraySyntax' => true]))->prettyPrintFile($ast);
        $output = preg_replace('/\)\s+:\s+/', '): ', $output);
        return [$output, $visitor];
    }
}
