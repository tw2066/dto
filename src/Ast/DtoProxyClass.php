<?php

declare(strict_types=1);

namespace Hyperf\DTO\Ast;

use Hyperf\ApiDocs\Exception\ApiDocsException;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Exception\Exception;
use Hyperf\Di\ReflectionManager;
use Hyperf\DTO\Annotation\Dto;
use Hyperf\DTO\Annotation\JSONField;
use Hyperf\DTO\DtoConfig;
use Hyperf\Support\Composer;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

class DtoProxyClass
{
    protected ?array $classJSONFieldArr = null;

    public function __construct(protected DtoConfig $dtoConfig)
    {
        $this->getGenericClass();
    }

    public function getGenericClass()
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

    public function generic()
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
            $this->genProxyFile();
            exit;
        }
    }

    public function genProxyFile()
    {
        $classes = AnnotationCollector::getClassesByAnnotation(Dto::class);
        /**
         * @var string $class
         * @var Dto $dtoAnnotation
         */
        foreach ($classes as $class => $dtoAnnotation) {
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
                if ($convert = $dtoAnnotation->requestType) {
                    $propertyInfo->alias = $convert->getValue($propertyName);
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
                if ($convert = $dtoAnnotation->responseType) {
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
            $this->putContents($class, $content);
        }
    }

    protected function putContents($generateNamespaceClassName, $content): void
    {
        $outputDir = $this->dtoConfig->getProxyDir();
        if (file_exists($outputDir) === false) {
            if (mkdir($outputDir, 0755, true) === false) {
                throw new \Exception("Failed to create a directory : {$outputDir}");
            }
        }
        $generateClassName = str_replace('\\', '_', $generateNamespaceClassName);
        $filename = $outputDir . $generateClassName . '.dto.proxy.php';
        file_put_contents($filename, $content);
    }

    protected function phpParser(string $classname, $filePath, $propertyArr, $isCreateJsonSerialize): string
    {
        $code = file_get_contents($filePath);
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

        try {
            $ast = $parser->parse($code);
        } catch (Error $error) {
            echo "Parse error: {$error->getMessage()}\n";
        }

        $traverser = new NodeTraverser();
        $resVisitor = \Hyperf\Support\make(DtoVisitor::class, [$classname, $propertyArr, $isCreateJsonSerialize]);
        $traverser->addVisitor($resVisitor);
        $ast = $traverser->traverse($ast);
        $prettyPrinter = new PrettyPrinter\Standard();
        return $prettyPrinter->prettyPrintFile($ast);
    }
}
