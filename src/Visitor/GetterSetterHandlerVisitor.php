<?php

declare(strict_types=1);

namespace Hyperf\DTO\Visitor;


use Hyperf\ApiDocs\ApiAnnotation;
use Hyperf\DTO\Annotation\Proxy\Data;
use Hyperf\Utils\CodeGen\PhpParser;
use Hyperf\Utils\Str;
use PhpParser\Node;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeVisitorAbstract;

class GetterSetterHandlerVisitor extends NodeVisitorAbstract
{
    /**
     * @var string[]
     */
    protected $getters = [];

    /**
     * @var string[]
     */
    protected $setters = [];


    public function beforeTraverse(array $nodes)
    {
        $methods = PhpParser::getInstance()->getAllMethodsFromStmts($nodes);

        $this->collectMethods($methods);
    }

    private function isGetterSetter(array $nodes)
    {
        $parts = [];
        $name = '';
        foreach ($nodes as $namespace) {
            if (!$namespace instanceof Node\Stmt\Namespace_) {
                continue;
            }
            $parts = $namespace->name->parts;
            foreach ($namespace->stmts as $class) {
                if (!$class instanceof Node\Stmt\Class_) {
                    continue;
                }
                $name = $class->name->name;
            }
        }
        $className = '';
        foreach ($parts as $part) {
            $className .= '\\' . $part;
        }
        $className .= '\\' . $name;
        $className = trim($className, '\\');
        $classAnnotation = ApiAnnotation::classMetadata($className);
        if (isset($classAnnotation[Data::class])) {
            return true;
        }
        return false;
    }

    public function afterTraverse(array $nodes)
    {
        if (!$this->isGetterSetter($nodes)) {
            return null;
        }
        foreach ($nodes as $namespace) {
            if (!$namespace instanceof Node\Stmt\Namespace_) {
                continue;
            }
            foreach ($namespace->stmts as $class) {
                if (!$class instanceof Node\Stmt\Class_) {
                    continue;
                }
                array_push($class->stmts, ...$this->buildGetterAndSetter($class->getProperties()));
            }
        }

        return $nodes;
    }

    /**
     * @param Property[] $data
     * @return Node\Stmt\ClassMethod[]
     */
    protected function buildGetterAndSetter(array $data): array
    {
        $stmts = [];
        foreach ($data as $column) {
            if ($name = $column->props[0]->name->name ?? '') {
                $getter = getter($name);
                if (!in_array($getter, $this->getters)) {
                    $stmts[] = $this->createGetter($getter, $name);
                }
                $setter = setter($name);
                if (!in_array($setter, $this->setters)) {
                    $stmts[] = $this->createSetter($setter, $name);
                }
            }
        }

        return $stmts;
    }

    protected function createGetter(string $method, string $name): Node\Stmt\ClassMethod
    {
        $node = new Node\Stmt\ClassMethod($method, ['flags' => Node\Stmt\Class_::MODIFIER_PUBLIC]);
        $node->stmts[] = new Node\Stmt\Return_(
            new Node\Expr\PropertyFetch(
                new Node\Expr\Variable('this'),
                new Node\Identifier($name)
            )
        );

        return $node;
    }

    protected function createSetter(string $method, string $name): Node\Stmt\ClassMethod
    {
        $node = new Node\Stmt\ClassMethod($method, [
            'flags' => Node\Stmt\Class_::MODIFIER_PUBLIC,
            'params' => [new Node\Param(new Node\Expr\Variable($name))],
        ]);
        $node->stmts[] = new Node\Stmt\Expression(
            new Node\Expr\Assign(
                new Node\Expr\PropertyFetch(
                    new Node\Expr\Variable('this'),
                    new Node\Identifier($name)
                ),
                new Node\Expr\Variable($name)
            )
        );
        $node->stmts[] = new Node\Stmt\Return_(
            new Node\Expr\Variable('this')
        );

        return $node;
    }

    protected function collectMethods(array $methods)
    {
        /** @var Node\Stmt\ClassMethod $method */
        foreach ($methods as $method) {
            $methodName = $method->name->name;
            if (Str::startsWith($methodName, 'get')) {
                $this->getters[] = $methodName;
            } elseif (Str::startsWith($methodName, 'set')) {
                $this->setters[] = $methodName;
            }
        }
    }
}
