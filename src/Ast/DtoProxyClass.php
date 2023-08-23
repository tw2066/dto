<?php

declare(strict_types=1);

namespace Hyperf\DTO\Ast;

use Hyperf\Collection\Arr;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Exception\Exception;
use Hyperf\Di\ReflectionManager;
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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

use function Hyperf\Support\make;

class DtoProxyClass
{
    protected ?array $classJSONFieldArr = null;

    public function __construct(protected DtoConfig $dtoConfig)
    {
        $this->getJSONFieldClass();
        $proxyDir = $this->dtoConfig->getProxyDir();
        if (file_exists($proxyDir) === false) {
            if (mkdir($proxyDir, 0755, true) === false) {
                throw new DtoException("Failed to create a directory : {$proxyDir}");
            }
        }
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
        $pid = pcntl_fork();
        if ($pid == -1) {
            throw new Exception('The process fork failed');
        }
        if ($pid) {
            pcntl_wait($status);
            $proxyDir = $this->dtoConfig->getProxyDir();
            if (! is_dir($proxyDir)) {
                return;
            }
            $finder = new Finder();
            $finder->files()->name('*.dto.proxy.php')->in($proxyDir);

            $classLoader = Composer::getLoader();
            $classMap = [];
            foreach ($finder->getIterator() as $value) {
                $classname = str_replace('_', '\\', $value->getBasename('.dto.proxy.php'));
                $classMap[$classname] = $value->getRealPath();
            }
            $classLoader->addClassMap($classMap);
            $classLoader->register(true);
        } else {
            $proxyDir = $this->dtoConfig->getProxyDir();
            $this->removeProxies($proxyDir);
            $this->genProxyFile();
            exit;
        }
    }

    protected function genProxyFile(): void
    {
        $classes = $this->getScanClass();

        foreach ($classes as $class) {
            /** @var Dto $dtoAnnotation */
            $dtoAnnotation = AnnotationCollector::getClassPropertyAnnotation($class, Dto::class);
            $rc = ReflectionManager::reflectClass($class);
            $files = new SplFileInfo($rc->getFileName());
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
            $this->putContents($class, $content, $files->getRealPath());
        }
    }

    protected function getScanClass(): array
    {
        $dtoClasses = AnnotationCollector::getClassesByAnnotation(Dto::class);
        $jsonFieldClass = $this->getJSONFieldClass();

        return Arr::merge(array_keys($dtoClasses), array_keys($jsonFieldClass));
    }

    protected function putContents($generateNamespaceClassName, $content, $realPath): void
    {
        // 适配PhpAccessor组件
        if (Str::contains($realPath, '/@')) {
            $filename = $realPath;
        } else {
            $outputDir = $this->dtoConfig->getProxyDir();
            $generateClassName = str_replace('\\', '_', $generateNamespaceClassName);
            $filename = $outputDir . $generateClassName . '.dto.proxy.php';
        }

        file_put_contents($filename, $content);
    }

    protected function phpParser(string $classname, $filePath, $propertyArr, $isCreateJsonSerialize): string
    {
        $code = file_get_contents($filePath);
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $ast = $parser->parse($code);

        $traverser = new NodeTraverser();
        $resVisitor = make(DtoVisitor::class, [$classname, $propertyArr, $isCreateJsonSerialize, $this->dtoConfig->getDtoDefaultValueLevel()]);
        $traverser->addVisitor($resVisitor);
        $ast = $traverser->traverse($ast);
        $prettyPrinter = new PrettyPrinter\Standard();
        return $prettyPrinter->prettyPrintFile($ast);
    }

    private function removeProxies($dir): void
    {
        $filesystem = new Filesystem();
        if (! $filesystem->exists($dir)) {
            return;
        }
        $finder = (new Finder())->files()->name('*.dto.proxy.php')->in($dir);
        $filesystem->remove($finder);
    }
}
