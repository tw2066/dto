<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Translation\Translator;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\ValidatorFactory;
use HyperfTest\DTO\Annotation\Validation\FakeTranslatorLoader;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 新测试的公共基类.
 *
 * 解决两个跨测试静态污染问题：
 * 1. ApplicationContext 一旦设置容器，Mapper::getJsonMapper() 会对过期容器调用
 *    get(StdoutLoggerInterface::class)，导致后续无关测试失败 —— 每个用例前后反射重置
 * 2. AnnotationCollector 中的注解会污染后续扫描类测试 —— 每个用例后清空
 */
abstract class AbstractTestCase extends TestCase
{
    protected array $tempFiles = [];

    protected array $tempDirs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetApplicationContext();
    }

    protected function tearDown(): void
    {
        $this->resetApplicationContext();
        AnnotationCollector::clear();
        \Mockery::close();
        Context::destroy(ResponseInterface::class);
        Context::destroy(ServerRequestInterface::class);
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        $this->tempFiles = [];
        foreach ($this->tempDirs as $dir) {
            $this->removeDir($dir);
        }
        $this->tempDirs = [];
        parent::tearDown();
    }

    protected function resetApplicationContext(): void
    {
        $property = new \ReflectionProperty(ApplicationContext::class, 'container');
        $property->setValue(null, null);
    }

    protected function makeValidatorFactory(array $translationGroups = []): ValidatorFactory
    {
        $translator = new Translator(new FakeTranslatorLoader($translationGroups), 'en');
        $translator->setFallback('en');
        return new ValidatorFactory($translator);
    }

    /**
     * 设置一个持有真实 ValidatorFactory 的容器到 ApplicationContext.
     */
    protected function setContainerWithValidatorFactory(array $translationGroups = []): ContainerInterface
    {
        $factory = $this->makeValidatorFactory($translationGroups);
        $container = \Mockery::mock(ContainerInterface::class);
        // 注意: Mockery 后声明的期望优先匹配，withAnyArgs 兜底必须先声明
        $container->shouldReceive('has')->withAnyArgs()->andReturn(false)->byDefault();
        $container->shouldReceive('has')->with(ValidatorFactoryInterface::class)->andReturn(true)->byDefault();
        $container->shouldReceive('get')->with(ValidatorFactoryInterface::class)->andReturn($factory)->byDefault();
        ApplicationContext::setContainer($container);
        return $container;
    }

    protected function createTempDir(string $prefix = 'dto_test_'): string
    {
        $dir = sys_get_temp_dir() . '/' . $prefix . uniqid();
        mkdir($dir, 0755, true);
        $this->tempDirs[] = $dir;
        return $dir;
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            is_dir($file) ? $this->removeDir($file) : @unlink($file);
        }
        @rmdir($dir);
    }
}
