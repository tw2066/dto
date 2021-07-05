<?php

declare(strict_types=1);

namespace Hyperf\DTO\Visitor;

use Hyperf\ApiDocs\ApiAnnotation;
use Hyperf\DTO\Annotation\Proxy\Data;
use Hyperf\DTO\Annotation\Proxy\Getter;
use Hyperf\DTO\Annotation\Proxy\Setter;
use Hyperf\Utils\CodeGen\PhpParser;
use Hyperf\Utils\Str;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeVisitorAbstract;
use Throwable;

class GetterSetterHandlerVisitor extends NodeVisitorAbstract
{
    /**
     * @var string[]
     */
    protected array $getters = [];

    /**
     * @var string[]
     */
    protected array $setters = [];

    protected bool $isGetter = false;

    protected bool $isSetter = false;

    public function beforeTraverse(array $nodes)
    {
        $methods = PhpParser::getInstance()->getAllMethodsFromStmts($nodes);

        $this->collectMethods($methods);
    }

    public function afterTraverse(array $nodes)
    {
        $this->setGetterSetter($nodes);
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
        foreach ($data as $property) {
            if ($name = $property->props[0]->name->name ?? '') {
                if ($this->isGetter) {
                    $getter = getter($name);
                    if (!in_array($getter, $this->getters)) {
                        $stmts[] = $this->createGetter($getter, $name);
                    }
                }
                if ($this->isSetter) {
                    $setter = setter($name);
                    if (!in_array($setter, $this->setters)) {
                        $stmts[] = $this->createSetter($setter, $name, clone $property);
                    }
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
                new Identifier($name)
            )
        );

        return $node;
    }

    protected function createSetter(string $method, string $name, Property $property): Node\Stmt\ClassMethod
    {
        if ($property->type instanceof Identifier) {
            $type = $property->type;
        } elseif ($property->type instanceof FullyQualified) {
            $parts = $property->type?->parts ?? [];
            $class = '';
            foreach ($parts as $part) {
                $class .= '\\' . $part;
            }
            $type = $class;
        } elseif ($property->type instanceof Name) {
            $parts = $property->type?->parts ?? [];
            $class = '';
            foreach ($parts as $part) {
                $class .= '\\' . $part;
            }
            $type = trim($class, '\\');
        } else {
            $type = $property->type?->parts[0] ?? [];
        }

        try {
            $comment = $property->getDocComment();
            if ($comment != null) {
                $text = $comment->getText();
                $text = str_replace('@var', '@param', $text);
                $property->setDocComment(new Doc($text));
            }
        } catch (Throwable) {
        }

        $node = new Node\Stmt\ClassMethod($method, [
            'flags' => Node\Stmt\Class_::MODIFIER_PUBLIC,
            'params' => [new Node\Param(new Node\Expr\Variable($name), null, $type)],
        ], $property->getAttributes());

        $node->stmts[] = new Node\Stmt\Expression(
            new Node\Expr\Assign(
                new Node\Expr\PropertyFetch(
                    new Node\Expr\Variable('this'),
                    new Identifier($name)
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

    private function setGetterSetter(array $nodes)
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
            $this->isGetter = true;
            $this->isSetter = true;
        }
        if (isset($classAnnotation[Getter::class])) {
            $this->isGetter = true;
        }
        if (isset($classAnnotation[Setter::class])) {
            $this->isSetter = true;
        }
    }
}
