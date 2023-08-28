<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\ApiDocs\Swagger\SwaggerCommon;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\MethodDefinitionCollector;
use Hyperf\Di\MethodDefinitionCollectorInterface;
use Hyperf\DTO\Scan\PropertyEnum;
use Hyperf\DTO\Scan\PropertyManager;
use HyperfTest\DTO\Request\Address;
use HyperfTest\DTO\Request\DemoBodyRequest;
use HyperfTest\DTO\Request\User;
use HyperfTest\DTO\Response\Activity;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @internal
 * @coversNothing
 */
class ScanAnnotationTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        AnnotationCollector::clear();
    }

    public function testScan()
    {
        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('has')->andReturn(true);
        $container->shouldReceive('get')->with(MethodDefinitionCollectorInterface::class)->andReturn(new MethodDefinitionCollector());

        $swaggerCommon = new SwaggerCommon();

        $propertyManager = new PropertyManager($swaggerCommon, new PropertyEnum());

        $property = $propertyManager->getProperty(DemoBodyRequest::class, 'int');
        $this->assertSame('int', $property->phpSimpleType);
        $this->assertSame(null, $property->className);
        $this->assertSame(true, $property->isSimpleType);

        $property = $propertyManager->getProperty(DemoBodyRequest::class, 'string');
        $this->assertSame('string', $property->phpSimpleType);
        $this->assertSame(null, $property->className);
        $this->assertSame(true, $property->isSimpleType);

        $property = $propertyManager->getProperty(DemoBodyRequest::class, 'arrClass');
        $this->assertSame('array', $property->phpSimpleType);
        $this->assertSame(Address::class, trim($property->arrClassName, '\\'));
        $this->assertSame(false, $property->isSimpleType);
        $this->assertSame(null, $property->arrSimpleType);

        $property = $propertyManager->getProperty(DemoBodyRequest::class, 'arrInt');
        $this->assertSame('array', $property->phpSimpleType);
        $this->assertSame(null, $property->arrClassName);
        $this->assertSame(null, $property->className);
        $this->assertSame(false, $property->isSimpleType);
        $this->assertSame('int', $property->arrSimpleType);

        $property = $propertyManager->getProperty(Address::class, 'user');
        $this->assertSame(null, $property->phpSimpleType);
        $this->assertSame(User::class, trim($property->className, '\\'));
        $this->assertSame(false, $property->isSimpleType);

        // return
        $property = $propertyManager->getProperty(Activity::class, 'id');
        $this->assertSame('string', $property->phpSimpleType);
        $this->assertSame(null, $property->className);
        $this->assertSame(true, $property->isSimpleType);
    }
}
