<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Annotation\MultipleAnnotation;
use Hyperf\DTO\Annotation\Validation\Max;
use Hyperf\DTO\JsonMapper;
use Hyperf\DTO\Scan\ValidationManager;
use PHPUnit\Framework\TestCase;

/**
 * 大小写变体键不得绕过校验写入属性。
 *
 * 修复点在 inspectProperty()：
 * 1. setter 分支要求声明名与查询名逐字符一致（PHP 方法名大小写不敏感，
 *    hasMethod('setNAME') 也能命中 setName()）；
 * 2. 移除属性的 strcasecmp 大小写不敏感回退匹配。
 *
 * @internal
 * @coversNothing
 */
class CaseVariantMappingTest extends TestCase
{
    public function testCaseVariantKeyDoesNotOverwriteValidatedValue(): void
    {
        $dto = $this->mapper()->simpleMap(['name' => 'ok', 'nAME' => 'TOO-LONG'], new CaseVariantSetterDto());

        $this->assertSame('ok', $dto->getName());
    }

    public function testSingleCaseVariantKeyIsIgnored(): void
    {
        $dto = $this->mapper()->simpleMap(['nAME' => 'EVIL'], new CaseVariantSetterDto());

        $this->assertSame('', $dto->getName());
    }

    public function testAllUpperCaseVariantKeyIsIgnored(): void
    {
        $dto = $this->mapper()->simpleMap(['NAME' => 'EVIL'], new CaseVariantSetterDto());

        $this->assertSame('', $dto->getName());
    }

    public function testUcfirstKeyIsIgnored(): void
    {
        $dto = $this->mapper()->simpleMap(['Name' => 'ignored'], new CaseVariantSetterDto());

        $this->assertSame('', $dto->getName());
    }

    public function testPropertyCaseInsensitiveFallbackRemoved(): void
    {
        // 无 setter 的公开属性：TITLE 对 title 的 strcasecmp 回退已移除
        $dto = $this->mapper()->simpleMap(['TITLE' => 'x'], new CaseVariantPropertyDto());

        $this->assertSame('', $dto->title);
    }

    public function testExactPropertyNameStillMaps(): void
    {
        $dto = $this->mapper()->simpleMap(['title' => 'x'], new CaseVariantPropertyDto());

        $this->assertSame('x', $dto->title);
    }

    public function testUnderscoreKeyIsIgnored(): void
    {
        $dto = $this->mapper()->simpleMap(['user_name' => 'ignored'], new CaseVariantUnderscoreDto());

        $this->assertSame('', $dto->getUserName());
    }

    public function testValidationRulesOnlyCoverDeclaredKeys(): void
    {
        // 基线行为（F03 规则登记已撤回）：规则键仅为声明名，nAME 等变体
        // 不在校验范围——因此映射层必须忽略变体键（本文件其余用例），
        // 二者共同构成 F02 的完整闭环。
        AnnotationCollector::clear();
        AnnotationCollector::collectProperty(
            CaseVariantSetterDto::class,
            'name',
            Max::class,
            new MultipleAnnotation(new Max(5))
        );
        try {
            $validationManager = new ValidationManager();
            $validationManager->generateValidation(CaseVariantSetterDto::class, 'name');

            $rules = $validationManager->getData(CaseVariantSetterDto::class)['rule'];
            $this->assertSame(['name'], array_keys($rules));
        } finally {
            AnnotationCollector::clear();
        }
    }

    private function mapper(): JsonMapper
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;
        return $mapper;
    }
}

class CaseVariantSetterDto
{
    private string $name = '';

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

class CaseVariantPropertyDto
{
    public string $title = '';
}

class CaseVariantUnderscoreDto
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
