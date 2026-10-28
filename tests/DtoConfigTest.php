<?php

declare(strict_types=1);

namespace HyperfTest\DTO;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\ScanHandler\PcntlScanHandler;
use Hyperf\Di\ScanHandler\ProcScanHandler;
use Hyperf\Di\ScanHandler\ScanHandlerInterface;
use Hyperf\DTO\DtoConfig;
use Hyperf\DTO\Type\Convert;
use Mockery as m;

/**
 * DtoConfig 配置读取测试（含顶层键与 dto/api_docs 键的坑）.
 *
 * @internal
 * @coversNothing
 */
class DtoConfigTest extends AbstractTestCase
{
    public function testDtoKeyMappedToPrivateProperties(): void
    {
        $config = $this->makeConfig([
            'dto' => ['proxy_dir' => '/tmp/abc/', 'dto_default_value_level' => 2],
        ]);
        $dtoConfig = new DtoConfig($config);

        $this->assertSame('/tmp/abc/', $dtoConfig->getProxyDir());
        $this->assertSame(2, $dtoConfig->getDtoDefaultValueLevel());
    }

    public function testEmptyDtoKeyFallsBackToApiDocs(): void
    {
        // dto 键为空时回退读 api_docs 键（与 api-docs 组件共享配置）
        $config = $this->makeConfig([
            'api_docs' => ['dto_default_value_level' => 1],
        ]);
        $dtoConfig = new DtoConfig($config);

        $this->assertSame(1, $dtoConfig->getDtoDefaultValueLevel());
    }

    public function testDtoKeyWinsOverApiDocs(): void
    {
        $config = $this->makeConfig([
            'dto' => ['dto_default_value_level' => 2],
            'api_docs' => ['dto_default_value_level' => 1],
        ]);
        $dtoConfig = new DtoConfig($config);

        $this->assertSame(2, $dtoConfig->getDtoDefaultValueLevel());
    }

    public function testScanCacheableReadsTopLevelKey(): void
    {
        // 注意: scan_cacheable 是顶层配置键（与 Hyperf 框架自身开关同名共享），不在 dto 键下
        $config = $this->makeConfig(['scan_cacheable' => true]);
        $this->assertTrue((new DtoConfig($config))->isScanCacheable());

        $config = $this->makeConfig(['scan_cacheable' => false]);
        $this->assertFalse((new DtoConfig($config))->isScanCacheable());
    }

    public function testProxyDirDefaultsToRuntimePath(): void
    {
        $config = $this->makeConfig();
        $dtoConfig = new DtoConfig($config);

        $this->assertSame(BASE_PATH . '/runtime/container/proxy/', $dtoConfig->getProxyDir());
    }

    public function testSetProxyDirAppendsTrailingSlash(): void
    {
        $config = $this->makeConfig();
        $dtoConfig = new DtoConfig($config);

        $dtoConfig->setProxyDir('/tmp/x');
        $this->assertSame('/tmp/x/', $dtoConfig->getProxyDir());

        $dtoConfig->setProxyDir('/tmp/y/');
        $this->assertSame('/tmp/y/', $dtoConfig->getProxyDir());
    }

    public function testResponsesGlobalConvertMappedToEnum(): void
    {
        $config = $this->makeConfig([
            'dto' => ['responses_global_convert' => 'snake'],
        ]);
        $dtoConfig = new DtoConfig($config);

        $this->assertSame(Convert::SNAKE, $dtoConfig->getResponsesGlobalConvert());
    }

    public function testResponsesGlobalConvertDefaultsToNull(): void
    {
        $config = $this->makeConfig();
        $this->assertNull((new DtoConfig($config))->getResponsesGlobalConvert());
    }

    public function testScanHandlerDefaultsByEnvironment(): void
    {
        $config = $this->makeConfig();
        $handler = (new DtoConfig($config))->getScanHandler();

        // 未定义 PHPUNIT_COMPOSER_INSTALL 常量时为 Pcntl，反之为 Proc
        if (defined('PHPUNIT_COMPOSER_INSTALL')) {
            $this->assertInstanceOf(ProcScanHandler::class, $handler);
        } else {
            $this->assertInstanceOf(PcntlScanHandler::class, $handler);
        }
    }

    public function testConfiguredScanHandlerTakesPrecedence(): void
    {
        $config = $this->makeConfig();
        $dtoConfig = new DtoConfig($config);

        $customHandler = m::mock(ScanHandlerInterface::class);
        $property = new \ReflectionProperty(DtoConfig::class, 'scan_handler');
        $property->setValue($dtoConfig, $customHandler);

        $this->assertSame($customHandler, $dtoConfig->getScanHandler());
    }

    private function makeConfig(array $values = []): ConfigInterface
    {
        $config = m::mock(ConfigInterface::class);
        $config->shouldReceive('get')->withAnyArgs()->andReturn(null)->byDefault();
        $config->shouldReceive('get')->with('scan_cacheable', false)
            ->andReturn($values['scan_cacheable'] ?? false)->byDefault();
        $config->shouldReceive('get')->with('dto', [])
            ->andReturn($values['dto'] ?? [])->byDefault();
        $config->shouldReceive('get')->with('api_docs', [])
            ->andReturn($values['api_docs'] ?? [])->byDefault();
        return $config;
    }
}
