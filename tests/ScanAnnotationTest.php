<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Listener;

use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\MethodDefinitionCollector;
use Hyperf\Di\MethodDefinitionCollectorInterface;
use Hyperf\DTO\Scan\PropertyManager;
use Hyperf\DTO\Scan\ScanAnnotation;
use Hyperf\Utils\Reflection\ClassInvoker;
use HyperfTest\DTO\Controller\DemoController;
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

        $scanAnnotation = new ScanAnnotation($container);

        /** @var ScanAnnotation $scanAnnotation */
        $scanAnnotation = new ClassInvoker($scanAnnotation);

        $scanAnnotation->scan(DemoController::class, 'add');

        $property = PropertyManager::getProperty(DemoBodyRequest::class, 'int');
        $this->assertSame('int', $property->type);
        $this->assertSame('int', $property->className);
        $this->assertSame(true, $property->isSimpleType);

        $property = PropertyManager::getProperty(DemoBodyRequest::class, 'string');
        $this->assertSame('string', $property->type);
        $this->assertSame('string', $property->className);
        $this->assertSame(true, $property->isSimpleType);

        $property = PropertyManager::getProperty(DemoBodyRequest::class, 'arr');
        $this->assertSame('array', $property->type);
        $this->assertSame(Address::class, trim($property->className, '\\'));
        $this->assertSame(false, $property->isSimpleType);

        $property = PropertyManager::getProperty(Address::class, 'user');
        $this->assertSame(User::class, $property->type);
        $this->assertSame(User::class, trim($property->className, '\\'));
        $this->assertSame(false, $property->isSimpleType);

        //return
        $property = PropertyManager::getProperty(Activity::class, 'id');
        $this->assertSame('string', $property->type);
        $this->assertSame('string', $property->className);
        $this->assertSame(true, $property->isSimpleType);
    }
}
