<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Scan;

use Hyperf\DTO\Annotation\Contracts\RequestBody;
use Hyperf\DTO\Annotation\Contracts\RequestFormData;
use Hyperf\DTO\Annotation\Contracts\RequestHeader;
use Hyperf\DTO\Annotation\Contracts\RequestQuery;
use Hyperf\DTO\Annotation\Contracts\Valid;
use Hyperf\DTO\DtoCommon;
use Hyperf\DTO\Exception\DtoException;
use Hyperf\DTO\Scan\MethodParametersManager;
use Hyperf\DTO\Scan\PropertyEnum;
use Hyperf\DTO\Scan\PropertyManager;
use HyperfTest\DTO\AbstractTestCase;
use HyperfTest\DTO\Request\Address;

/**
 * MethodParametersManager 互斥约束校验 + scanClassMethodParam 参数类型解析测试.
 *
 * @internal
 * @coversNothing
 */
class MethodParametersManagerTest extends AbstractTestCase
{
    // ---------------- setMethodParameters 互斥校验 ----------------

    public function testSameParameterWithQueryAndBodyThrows(): void
    {
        $manager = $this->makeManager();

        $this->expectException(DtoException::class);
        $this->expectExceptionMessage('cannot exist simultaneously');
        $manager->setMethodParameters(MpmConflictController::class, 'handle');
    }

    public function testTwoHeaderParametersInSameMethodThrow(): void
    {
        $manager = $this->makeManager();

        $this->expectException(DtoException::class);
        $this->expectExceptionMessage('[RequestHeader] can only exist');
        $manager->setMethodParameters(MpmHeaderConflictController::class, 'handle');
    }

    public function testFormDataAndBodyAcrossParametersThrow(): void
    {
        $manager = $this->makeManager();

        $this->expectException(DtoException::class);
        $this->expectExceptionMessage('Method annotation [RequestFormData RequestBody] cannot exist simultaneously');
        $manager->setMethodParameters(MpmMethodConflictController::class, 'handle');
    }

    public function testOnlyValidAnnotationIsRegistered(): void
    {
        $manager = $this->makeManager();
        $manager->setMethodParameters(MpmOnlyValidController::class, 'handle');

        $methodParameter = $manager->getMethodParameter(MpmOnlyValidController::class, 'handle', 'q');
        $this->assertNotNull($methodParameter);
        $this->assertTrue($methodParameter->isValid());
        $this->assertFalse($methodParameter->isRequestBody());
        $this->assertFalse($methodParameter->isRequestQuery());
        $this->assertFalse($methodParameter->isRequestFormData());
        $this->assertFalse($methodParameter->isRequestHeader());
    }

    public function testSetContentSkipsWhenAlreadyRegistered(): void
    {
        $manager = $this->makeManager();
        $manager->setMethodParameters(MpmSkipController::class, 'handle');
        $first = $manager->getMethodParameter(MpmSkipController::class, 'handle', 'q');

        // 二次扫描同方法：setContent 已存在则跳过，返回同一实例
        $manager->setMethodParameters(MpmSkipController::class, 'handle');
        $second = $manager->getMethodParameter(MpmSkipController::class, 'handle', 'q');

        $this->assertSame($first, $second);
    }

    public function testUnregisteredParameterReturnsNull(): void
    {
        $manager = $this->makeManager();
        $manager->setMethodParameters(MpmUnregisteredController::class, 'handle');

        $this->assertNull($manager->getMethodParameter(MpmUnregisteredController::class, 'handle', 'q'));
    }

    // ---------------- scanClassMethodParam 参数类型解析 ----------------

    public function testClassArrayParamResolvedFromDocblock(): void
    {
        $manager = $this->makeManager();
        $manager->scanClassMethodParam(MpmArrayController::class, 'handle');

        $property = $manager->getProperty(MpmArrayController::class, 'handle', 'list');
        $this->assertNotNull($property);
        $this->assertSame(Address::class, $property->arrClassName);
        $this->assertFalse($property->isSimpleType);
        $this->assertNull($property->arrSimpleType);
    }

    public function testSimpleArrayParamResolvedFromDocblock(): void
    {
        $manager = $this->makeManager();
        $manager->scanClassMethodParam(MpmIntArrayController::class, 'handle');

        $property = $manager->getProperty(MpmIntArrayController::class, 'handle', 'nums');
        $this->assertSame('int', $property->arrSimpleType);
        $this->assertFalse($property->isSimpleType);
        $this->assertNull($property->arrClassName);
    }

    public function testUnionDocblockTypeIsTruncatedToFirst(): void
    {
        $manager = $this->makeManager();
        $manager->scanClassMethodParam(MpmUnionFirstWinsController::class, 'handle');

        // 联合类型取第一个: Address[]|int[] → Address[]
        $property = $manager->getProperty(MpmUnionFirstWinsController::class, 'handle', 'list');
        $this->assertSame(Address::class, $property->arrClassName);
    }

    public function testUnionDocblockWithNullFirstLosesClassArrayType(): void
    {
        // src 现状（坑）: 联合类型简单按 | 截断取第一个，null|Address[] 截出 'null'，
        // 类数组类型信息丢失（此用例锁定该行为，修复后应翻转）
        $manager = $this->makeManager();
        $manager->scanClassMethodParam(MpmUnionArrayController::class, 'handle');

        $property = $manager->getProperty(MpmUnionArrayController::class, 'handle', 'list');
        $this->assertNull($property->arrClassName);
    }

    public function testClassTypeParam(): void
    {
        $manager = $this->makeManager();
        $manager->scanClassMethodParam(MpmClassParamController::class, 'handle');

        $property = $manager->getProperty(MpmClassParamController::class, 'handle', 'address');
        $this->assertSame(Address::class, $property->className);
        $this->assertFalse($property->isSimpleType);
    }

    public function testEnumTypeParam(): void
    {
        $manager = $this->makeManager();
        $manager->scanClassMethodParam(MpmEnumParamController::class, 'handle');

        $property = $manager->getProperty(MpmEnumParamController::class, 'handle', 'status');
        $this->assertNotNull($property->enum);
        $this->assertFalse($property->isSimpleType);
    }

    public function testSimpleTypeParam(): void
    {
        $manager = $this->makeManager();
        $manager->scanClassMethodParam(MpmSimpleParamController::class, 'handle');

        $property = $manager->getProperty(MpmSimpleParamController::class, 'handle', 's');
        $this->assertSame('string', $property->phpSimpleType);
        $this->assertTrue($property->isSimpleType);
    }

    public function testScanIsIdempotentAndToleratesLeadingBackslash(): void
    {
        $manager = $this->makeManager();
        $manager->scanClassMethodParam(MpmArrayController::class, 'handle');
        // 二次调用早退；类名带前导 \ 被 ltrim
        $manager->scanClassMethodParam('\\' . MpmArrayController::class, 'handle');

        $property = $manager->getProperty(MpmArrayController::class, 'handle', 'list');
        $this->assertSame(Address::class, $property->arrClassName);
    }

    private function makeManager(): MethodParametersManager
    {
        $dtoCommon = new DtoCommon();
        $propertyEnum = new PropertyEnum();
        $propertyManager = new PropertyManager($dtoCommon, $propertyEnum);
        return new MethodParametersManager($dtoCommon, $propertyEnum, $propertyManager);
    }
}

class MpmConflictController
{
    public function handle(#[RequestQuery] #[RequestBody] string $q) {}
}

class MpmHeaderConflictController
{
    public function handle(#[RequestHeader] string $a, #[RequestHeader] string $b) {}
}

class MpmMethodConflictController
{
    public function handle(#[RequestFormData] string $a, #[RequestBody] string $b) {}
}

class MpmOnlyValidController
{
    public function handle(#[Valid] string $q) {}
}

class MpmSkipController
{
    public function handle(#[Valid] string $q) {}
}

class MpmUnregisteredController
{
    public function handle(string $q) {}
}

class MpmArrayController
{
    /**
     * @param Address[] $list
     */
    public function handle(array $list) {}
}

class MpmIntArrayController
{
    /**
     * @param int[] $nums
     */
    public function handle(array $nums) {}
}

class MpmUnionArrayController
{
    /**
     * @param null|Address[] $list
     */
    public function handle(array $list) {}
}

class MpmUnionFirstWinsController
{
    /**
     * @param Address[]|int[] $list
     */
    public function handle(array $list) {}
}

class MpmClassParamController
{
    public function handle(Address $address) {}
}

enum MpmStatusEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

class MpmEnumParamController
{
    public function handle(MpmStatusEnum $status) {}
}

class MpmSimpleParamController
{
    public function handle(string $s) {}
}
