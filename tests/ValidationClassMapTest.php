<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\DTO\Annotation\Validation\Required;
use Hyperf\DTO\DtoCommon;
use Hyperf\DTO\DtoValidation;
use Hyperf\DTO\Mapper;
use Hyperf\DTO\Scan\PropertyEnum;
use Hyperf\DTO\Scan\PropertyManager;
use Hyperf\DTO\Scan\ValidationManager;
use Hyperf\Validation\ValidationException;

/**
 * @internal
 * @coversNothing
 */
class ValidationClassMapTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        $mapper = Mapper::getJsonMapper();
        $mapper->classMap = [];
        $mapper->classFactories = [];
        parent::tearDown();
    }

    public function testInterfaceClassMapValidatesConcreteRules(): void
    {
        (new Required())->collectProperty(ValidationMappedCard::class, 'number');
        $this->setContainerWithValidatorFactory();
        $validation = $this->makeValidation(ValidationMappedRequest::class);

        Mapper::getJsonMapper()->classMap[ValidationMappedInterface::class] = ValidationMappedCard::class;

        try {
            $validation->validate(ValidationMappedRequest::class, ['method' => ['number' => '']]);
            $this->fail('Expected concrete class rule to be validated.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        $input = ['method' => ['number' => '4111']];
        $validation->validate(ValidationMappedRequest::class, $input);
        $dto = Mapper::map($input, new ValidationMappedRequest());
        $this->assertInstanceOf(ValidationMappedCard::class, $dto->method);
        $this->assertSame('4111', $dto->method->number);
    }

    public function testInterfaceClassMapAcceptsLeadingSlashKey(): void
    {
        (new Required())->collectProperty(ValidationMappedCard::class, 'number');
        $this->setContainerWithValidatorFactory();
        $validation = $this->makeValidation(ValidationMappedRequest::class);

        Mapper::getJsonMapper()->classMap['\\' . ValidationMappedInterface::class] = ValidationMappedCard::class;

        try {
            $validation->validate(ValidationMappedRequest::class, ['method' => ['number' => '']]);
            $this->fail('Expected concrete class rule to be validated.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        $validation->validate(ValidationMappedRequest::class, ['method' => ['number' => '4111']]);
        $this->assertTrue(true);
    }

    public function testAbstractClassMapValidatesConcreteRules(): void
    {
        (new Required())->collectProperty(ValidationMappedConcrete::class, 'number');
        $this->setContainerWithValidatorFactory();
        $validation = $this->makeValidation(ValidationMappedAbstractRequest::class);

        Mapper::getJsonMapper()->classMap[ValidationMappedBase::class] = ValidationMappedConcrete::class;

        try {
            $validation->validate(ValidationMappedAbstractRequest::class, ['method' => ['number' => '']]);
            $this->fail('Expected concrete class rule to be validated.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        $input = ['method' => ['number' => '4111']];
        $validation->validate(ValidationMappedAbstractRequest::class, $input);
        $dto = Mapper::map($input, new ValidationMappedAbstractRequest());
        $this->assertInstanceOf(ValidationMappedConcrete::class, $dto->method);
        $this->assertSame('4111', $dto->method->number);
    }

    public function testInterfaceClassArrayValidatesConcreteRules(): void
    {
        (new Required())->collectProperty(ValidationMappedCard::class, 'number');
        $this->setContainerWithValidatorFactory();
        $validation = $this->makeValidation(ValidationMappedArrayRequest::class);

        Mapper::getJsonMapper()->classMap[ValidationMappedInterface::class] = ValidationMappedCard::class;

        try {
            $validation->validate(ValidationMappedArrayRequest::class, ['methods' => [['number' => '']]]);
            $this->fail('Expected concrete array element rules to be validated.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        $validation->validate(ValidationMappedArrayRequest::class, ['methods' => [['number' => '4111']]]);
        $dto = Mapper::map(['methods' => [['number' => '4111']]], new ValidationMappedArrayRequest());
        $this->assertInstanceOf(ValidationMappedCard::class, $dto->methods[0]);
        $this->assertSame('4111', $dto->methods[0]->number);
    }

    public function testInterfaceBroadSetterValidatesAndMapsConcreteClass(): void
    {
        (new Required())->collectProperty(ValidationMappedCard::class, 'number');
        $this->setContainerWithValidatorFactory();
        $validation = $this->makeValidation(ValidationBroadSetterRequest::class);

        Mapper::getJsonMapper()->classMap[ValidationMappedInterface::class] = ValidationMappedCard::class;

        try {
            $validation->validate(ValidationBroadSetterRequest::class, ['method' => ['number' => '']]);
            $this->fail('Expected concrete class rule to be validated.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        // 宽泛 setter（mixed）回退到属性声明的 interface，再按 classMap 映射具体类
        $input = ['method' => ['number' => '4111']];
        $validation->validate(ValidationBroadSetterRequest::class, $input);
        $dto = Mapper::map($input, new ValidationBroadSetterRequest());
        $this->assertInstanceOf(ValidationMappedCard::class, $dto->getMethod());
        $this->assertSame('4111', $dto->getMethod()->number);
    }

    public function testInterfaceArrayWithRawArraySetterValidatesAndMapsConcreteClass(): void
    {
        (new Required())->collectProperty(ValidationMappedCard::class, 'number');
        $this->setContainerWithValidatorFactory();
        $validation = $this->makeValidation(ValidationIfaceArraySetterRequest::class);

        Mapper::getJsonMapper()->classMap[ValidationMappedInterface::class] = ValidationMappedCard::class;

        try {
            $validation->validate(ValidationIfaceArraySetterRequest::class, ['methods' => [['number' => '']]]);
            $this->fail('Expected concrete array element rules to be validated.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        // 元素类型只写在属性 @var 上、setter 是裸 array 时，映射计划消费 arrInterfaceName
        $input = ['methods' => [['number' => '4111']]];
        $validation->validate(ValidationIfaceArraySetterRequest::class, $input);
        $dto = Mapper::map($input, new ValidationIfaceArraySetterRequest());
        $this->assertInstanceOf(ValidationMappedCard::class, $dto->getMethods()[0]);
        $this->assertSame('4111', $dto->getMethods()[0]->number);
    }

    public function testSetterOnlyInterfacePropertyValidatesAndMapsConcreteClass(): void
    {
        (new Required())->collectProperty(ValidationMappedCard::class, 'number');
        $this->setContainerWithValidatorFactory();
        $validation = $this->makeValidation(ValidationSetterOnlyIfaceRequest::class);

        Mapper::getJsonMapper()->classMap[ValidationMappedInterface::class] = ValidationMappedCard::class;

        try {
            $validation->validate(ValidationSetterOnlyIfaceRequest::class, ['method' => ['number' => '']]);
            $this->fail('Expected concrete class rule to be validated.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        $input = ['method' => ['number' => '4111']];
        $validation->validate(ValidationSetterOnlyIfaceRequest::class, $input);
        $dto = Mapper::map($input, new ValidationSetterOnlyIfaceRequest());
        $this->assertInstanceOf(ValidationMappedCard::class, $dto->getMethod());
    }

    public function testNestedObjectScalarValueIsLeftToParentRules(): void
    {
        $this->setContainerWithValidatorFactory();
        $validation = $this->makeValidation(ValidationScalarParent::class);

        // 非数组值不再递归进入嵌套 DTO 验证，避免抛未捕获 DtoException。
        $validation->validate(ValidationScalarParent::class, ['child' => 'oops']);
        $this->assertTrue(true);
    }

    public function testInterfacePropertyWithoutClassMapIsIgnored(): void
    {
        $this->setContainerWithValidatorFactory();
        $validation = $this->makeValidation(ValidationMappedRequest::class);

        // 没有 classMap 时 interface 本身没有规则，不应新增误报或 DtoException。
        $validation->validate(ValidationMappedRequest::class, ['method' => ['number' => '']]);
        $this->assertTrue(true);
    }

    public function testScalarElementsInInterfaceClassArrayAreSkipped(): void
    {
        $this->setContainerWithValidatorFactory();
        $validation = $this->makeValidation(ValidationMappedArrayRequest::class);

        Mapper::getJsonMapper()->classMap[ValidationMappedInterface::class] = ValidationMappedCard::class;

        // interface 数组的标量/null 叶子继续跳过，不调用 classMap。
        $validation->validate(ValidationMappedArrayRequest::class, ['methods' => ['oops', null]]);
        $this->assertTrue(true);
    }

    private function makeValidation(string $className): DtoValidation
    {
        $propertyManager = new PropertyManager(new DtoCommon(), new PropertyEnum());
        $propertyManager->scanClassProperties($className);

        return new DtoValidation($propertyManager, new ValidationManager());
    }
}

interface ValidationMappedInterface {}

class ValidationMappedCard implements ValidationMappedInterface
{
    public string $number = '';
}

class ValidationMappedRequest
{
    public ValidationMappedInterface $method;
}

abstract class ValidationMappedBase
{
    public string $id = '';
}

class ValidationMappedConcrete extends ValidationMappedBase
{
    public string $number = '';
}

class ValidationMappedAbstractRequest
{
    public ValidationMappedBase $method;
}

class ValidationMappedArrayRequest
{
    /** @var ValidationMappedInterface[] */
    public array $methods = [];
}

class ValidationScalarChild
{
    public string $x = '';
}

class ValidationScalarParent
{
    public ValidationScalarChild $child;
}

class ValidationBroadSetterRequest
{
    private ValidationMappedInterface $method;

    public function setMethod(mixed $method): void
    {
        $this->method = $method;
    }

    public function getMethod(): ValidationMappedInterface
    {
        return $this->method;
    }
}

class ValidationIfaceArraySetterRequest
{
    /** @var ValidationMappedInterface[] */
    private array $methods = [];

    public function setMethods(array $methods): void
    {
        $this->methods = $methods;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }
}

class ValidationSetterOnlyIfaceRequest
{
    private array $bag = [];

    public function setMethod(ValidationMappedInterface $method): void
    {
        $this->bag['method'] = $method;
    }

    public function getMethod(): ?ValidationMappedInterface
    {
        return $this->bag['method'] ?? null;
    }
}
