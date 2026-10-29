<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Ast;

use Hyperf\Contract\ConfigInterface;
use Hyperf\DTO\Annotation\Dto;
use Hyperf\DTO\Annotation\JSONField;
use Hyperf\DTO\Ast\DtoProxyClass;
use Hyperf\DTO\DtoConfig;
use HyperfTest\DTO\AbstractTestCase;
use HyperfTest\DTO\Ast\Fixtures\ProxyAliasDto;
use HyperfTest\DTO\Ast\Fixtures\ProxyArrayTypeAliasDto;
use HyperfTest\DTO\Ast\Fixtures\ProxyDtoAnnotatedDto;
use HyperfTest\DTO\Ast\Fixtures\ProxyExistingSerializeDto;
use Mockery as m;

/**
 * DtoProxyClass::genProxyFile 集成测试（真实写文件到临时目录）.
 *
 * generic() 含 exit() 不测；这里覆盖代理生成的核心逻辑。
 *
 * @internal
 * @coversNothing
 */
class DtoProxyClassTest extends AbstractTestCase
{
    public function testProxyContainsAliasJsonSerializeWithoutInputSetter(): void
    {
        (new JSONField('user_name'))->collectProperty(ProxyAliasDto::class, 'name');
        $proxyDir = $this->createTempDir();
        $proxyClass = new TestableDtoProxyClass($this->makeDtoConfig($proxyDir));

        $proxyClass->exposeGenProxyFile();

        $proxyFile = $this->proxyFilePath($proxyDir, ProxyAliasDto::class);
        $this->assertFileExists($proxyFile);
        $content = file_get_contents($proxyFile);
        $this->assertStringNotContainsString('_set_dto_alias_', $content);
        // jsonSerialize 生成: key 为别名，有 getter 走 getter
        $this->assertStringContainsString('function jsonSerialize()', $content);
        $this->assertStringContainsString("'user_name' => \$this->getName()", $content);
        $this->assertStringContainsString('implements \JsonSerializable', $content);
    }

    public function testProxyAliasArrayKeepsResponseWithoutInputSetter(): void
    {
        (new JSONField('user_items'))->collectProperty(ProxyArrayTypeAliasDto::class, 'items');
        $proxyDir = $this->createTempDir();
        $proxyClass = new TestableDtoProxyClass($this->makeDtoConfig($proxyDir));

        $proxyClass->exposeGenProxyFile();

        $content = file_get_contents($this->proxyFilePath($proxyDir, ProxyArrayTypeAliasDto::class));
        $this->assertStringContainsString("'user_items' => \$this->items", $content);
        $this->assertStringNotContainsString('_set_dto_alias_', $content);
        $this->assertStringNotContainsString('@param \\HyperfTest\\DTO\\Ast\\Fixtures\\ProxyArrayTypeItem[] $param', $content);
    }

    public function testProxySkippedWhenFresh(): void
    {
        (new JSONField('user_name'))->collectProperty(ProxyAliasDto::class, 'name');
        $proxyDir = $this->createTempDir();
        $proxyClass = new TestableDtoProxyClass($this->makeDtoConfig($proxyDir));
        $proxyClass->exposeGenProxyFile();
        $proxyFile = $this->proxyFilePath($proxyDir, ProxyAliasDto::class);
        $this->assertFileExists($proxyFile);

        // proxy mtime >= 源文件 mtime 时跳过再生成
        $content = file_get_contents($proxyFile) . "\n// MANUAL MARKER";
        file_put_contents($proxyFile, $content);
        $proxyClass->exposeGenProxyFile();
        $this->assertSame($content, file_get_contents($proxyFile));
    }

    public function testExistingJsonSerializeIsNotDuplicated(): void
    {
        (new JSONField('dto_title'))->collectProperty(ProxyExistingSerializeDto::class, 'title');
        $proxyDir = $this->createTempDir();
        $proxyClass = new TestableDtoProxyClass($this->makeDtoConfig($proxyDir));

        $proxyClass->exposeGenProxyFile();

        $proxyFile = $this->proxyFilePath($proxyDir, ProxyExistingSerializeDto::class);
        $this->assertFileExists($proxyFile);
        $content = file_get_contents($proxyFile);
        // 已有 jsonSerialize 的类不再生成，也不再生成输入别名 setter
        $this->assertSame(1, substr_count($content, 'function jsonSerialize'));
        $this->assertStringNotContainsString('_set_dto_alias_', $content);
    }

    public function testFreshProxyIsReusedWithoutVersionCheck(): void
    {
        (new JSONField('user_name'))->collectProperty(ProxyAliasDto::class, 'name');
        $proxyDir = $this->createTempDir();
        $proxyClass = new TestableDtoProxyClass($this->makeDtoConfig($proxyDir));
        $proxyClass->exposeGenProxyFile();
        $proxyFile = $this->proxyFilePath($proxyDir, ProxyAliasDto::class);

        $content = '<?php // existing proxy content';
        file_put_contents($proxyFile, $content);
        touch($proxyFile, time() + 10);
        $proxyClass->exposeGenProxyFile();

        $this->assertSame($content, file_get_contents($proxyFile));
    }

    public function testDtoAnnotatedAndJsonFieldClassesBothGenerated(): void
    {
        // getScanClass 合并: Dto 注解类 ∪ JSONField 属性类
        (new Dto())->collectClass(ProxyDtoAnnotatedDto::class);
        (new JSONField('user_name'))->collectProperty(ProxyAliasDto::class, 'name');
        $proxyDir = $this->createTempDir();
        $proxyClass = new TestableDtoProxyClass($this->makeDtoConfig($proxyDir));

        $proxyClass->exposeGenProxyFile();

        $this->assertFileExists($this->proxyFilePath($proxyDir, ProxyDtoAnnotatedDto::class));
        $this->assertFileExists($this->proxyFilePath($proxyDir, ProxyAliasDto::class));
    }

    private function makeDtoConfig(string $proxyDir): DtoConfig
    {
        $config = m::mock(ConfigInterface::class);
        $config->shouldReceive('get')->withAnyArgs()->andReturn(null)->byDefault();
        $config->shouldReceive('get')->with('scan_cacheable', false)->andReturn(false)->byDefault();
        $config->shouldReceive('get')->with('dto', [])->andReturn(['proxy_dir' => $proxyDir . '/'])->byDefault();
        $config->shouldReceive('get')->with('api_docs', [])->andReturn([])->byDefault();
        return new DtoConfig($config);
    }

    private function proxyFilePath(string $proxyDir, string $class): string
    {
        return $proxyDir . '/' . str_replace('\\', '_', $class) . '.dto.proxy.php';
    }
}

class TestableDtoProxyClass extends DtoProxyClass
{
    public function exposeGenProxyFile(): void
    {
        $this->genProxyFile();
    }
}
