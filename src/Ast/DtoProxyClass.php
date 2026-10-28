<?php

declare(strict_types=1);

namespace Hyperf\DTO\Ast;

use Hyperf\Collection\Arr;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\ReflectionManager;
use Hyperf\Di\ScanHandler\ScanHandlerInterface;
use Hyperf\DTO\Annotation\Dto;
use Hyperf\DTO\Annotation\JSONField;
use Hyperf\DTO\DtoConfig;
use Hyperf\DTO\Exception\DtoException;
use Hyperf\Stringable\Str;
use Hyperf\Support\Composer;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

use function Hyperf\Support\make;

class DtoProxyClass
{
    private const PROXY_MARKER = 'dto-proxy-version: 4.0';

    protected ?array $classJSONFieldArr = null;

    protected ScanHandlerInterface $handler;

    public function __construct(protected DtoConfig $dtoConfig)
    {
        $this->getJSONFieldClass();
        $proxyDir = $this->dtoConfig->getProxyDir();
        if (file_exists($proxyDir) === false) {
            if (mkdir($proxyDir, 0755, true) === false) {
                throw new DtoException("Failed to create a directory : {$proxyDir}");
            }
        }
        $this->handler = $this->dtoConfig->getScanHandler();
    }

    public function getJSONFieldClass(): ?array
    {
        if ($this->classJSONFieldArr === null) {
            $arr = [];
            $classes = AnnotationCollector::getPropertiesByAnnotation(JSONField::class);
            foreach ($classes as $class) {
                $classname = $class['class'];
                $arr[$classname][$class['property']] = $class['annotation'];
            }
            $this->classJSONFieldArr = $arr;
        }
        return $this->classJSONFieldArr;
    }

    public function generic(): void
    {
        $scanned = $this->handler->scan();
        $proxyDir = $this->dtoConfig->getProxyDir();
        if ($scanned->isScanned()) {
            if (! is_dir($proxyDir)) {
                return;
            }
            $finder = new Finder();
            $finder->files()->name('*.dto.proxy.php')->in($proxyDir);

            $classLoader = Composer::getLoader();
            $classMap = [];
            foreach ($finder->getIterator() as $value) {
                if (! $this->isCurrentProxyFile($value->getPathname())) {
                    continue;
                }
                $classname = str_replace('_', '\\', $value->getBasename('.dto.proxy.php'));
                $classMap[$classname] = $value->getPathname();
            }
            $classLoader->addClassMap($classMap);
            $classLoader->register(true);
            return;
        }
        // 代理文件齐全且开启扫描缓存时跳过(视为构建期产物); 否则走生成逻辑(内部按版本与源文件 mtime 增量跳过)
        if (! $this->dtoConfig->isScanCacheable() || $this->hasInvalidProxyFile()) {
            // $this->removeProxies($proxyDir);
            $this->genProxyFile();
        }

        exit;
    }

    /**
     * 是否存在缺失或旧版代理文件: 旧版本不能被扫描缓存直接复用.
     */
    protected function hasInvalidProxyFile(): bool
    {
        foreach ($this->getScanClass() as $class) {
            $rc = ReflectionManager::reflectClass($class);
            $files = new SplFileInfo($rc->getFileName());
            $proxyFile = $this->getProxyClassFilePath($class, $files->getRealPath());
            if (! $this->isCurrentProxyFile($proxyFile)) {
                return true;
            }
        }
        return false;
    }

    protected function genProxyFile(): void
    {
        $classes = $this->getScanClass();

        foreach ($classes as $class) {
            /** @var Dto $dtoAnnotation */
            $dtoAnnotation = AnnotationCollector::getClassAnnotation($class, Dto::class);
            $rc = ReflectionManager::reflectClass($class);
            $files = new SplFileInfo($rc->getFileName());
            $proxyClassFilePath = $this->getProxyClassFilePath($class, $files->getRealPath());
            if (file_exists($proxyClassFilePath)
                && filemtime($proxyClassFilePath) >= $files->getMTime()
                && $this->isCurrentProxyFile($proxyClassFilePath)
            ) {
                continue;
            }
            $arr = [];
            $isCreateJsonSerialize = false;
            foreach ($rc->getProperties() as $property) {
                $propertyInfo = new PropertyInfo();
                $propertyName = $property->name;
                $propertyInfo->propertyName = $propertyName;

                $getMethodName = \Hyperf\Support\getter($property->name);
                if ($rc->hasMethod($getMethodName)) {
                    $propertyInfo->getMethodName = $getMethodName;
                }
                if (isset($this->classJSONFieldArr[$class][$propertyName])) {
                    /** @var JSONField $JSONField */
                    $JSONField = $this->classJSONFieldArr[$class][$propertyName];
                    if ($JSONField->name != $propertyName) {
                        $propertyInfo->alias = $JSONField->name;
                    }
                } elseif (! $property->isPublic()) {
                    $propertyInfo->isJsonSerialize = false;
                }
                $propertyInfo->jsonArrKey = $propertyName;
                if ($convert = $this->dtoConfig->getResponsesGlobalConvert()) {
                    $isCreateJsonSerialize = true;
                    $propertyInfo->jsonArrKey = $convert->getValue($propertyName);
                }
                if ($convert = $dtoAnnotation?->responseConvert) {
                    $isCreateJsonSerialize = true;
                    $propertyInfo->jsonArrKey = $convert->getValue($propertyName);
                }
                if ($propertyInfo->alias) {
                    $isCreateJsonSerialize = true;
                    $propertyInfo->jsonArrKey = $propertyInfo->alias;
                }

                $arr[$propertyName] = $propertyInfo;
            }

            if ($rc->hasMethod('jsonSerialize')) {
                $isCreateJsonSerialize = false;
            }
            $content = $this->phpParser($class, $files->getRealPath(), $arr, $isCreateJsonSerialize);
            $content = substr_replace($content, "<?php\n// " . self::PROXY_MARKER, 0, 5);
            file_put_contents($proxyClassFilePath, $content);
        }
    }

    protected function getScanClass(): array
    {
        $dtoClasses = AnnotationCollector::getClassesByAnnotation(Dto::class);
        $jsonFieldClass = $this->getJSONFieldClass();

        return Arr::merge(array_keys($dtoClasses), array_keys($jsonFieldClass));
    }

    protected function getProxyClassFilePath($generateNamespaceClassName, $realPath): string
    {
        // 适配PhpAccessor组件
        if (Str::contains($realPath, '/@')) {
            $filename = $realPath;
        } else {
            $outputDir = $this->dtoConfig->getProxyDir();
            $generateClassName = str_replace('\\', '_', $generateNamespaceClassName);
            $filename = $outputDir . $generateClassName . '.dto.proxy.php';
        }
        return $filename;
    }

    protected function phpParser(string $classname, $filePath, $propertyArr, $isCreateJsonSerialize): string
    {
        $code = file_get_contents($filePath);
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $traverser = new NodeTraverser();
        $resVisitor = make(DtoVisitor::class, [$classname, $propertyArr, $isCreateJsonSerialize, $this->dtoConfig->getDtoDefaultValueLevel()]);
        $traverser->addVisitor($resVisitor);
        $ast = $traverser->traverse($ast);
        $prettyPrinter = new PrettyPrinter\Standard(['shortArraySyntax' => true]);
        return $prettyPrinter->prettyPrintFile($ast);
    }

    private function isCurrentProxyFile(string $filename): bool
    {
        if (! is_file($filename)) {
            return false;
        }
        $content = file_get_contents($filename, false, null, 0, 256);
        return is_string($content) && str_contains($content, '// ' . self::PROXY_MARKER);
    }

    private function removeProxies($dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $finder = (new Finder())->files()->name('*.dto.proxy.php')->in($dir);
        $files = $finder->files();
        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($files as $file) {
            unlink($file->getRealPath());
        }
    }
}
