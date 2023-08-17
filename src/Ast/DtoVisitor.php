<?php

declare(strict_types=1);

namespace Hyperf\DTO\Ast;

use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use function Hyperf\Support\setter;

class DtoVisitor extends NodeVisitorAbstract
{
    public BuilderFactory $factory;

    /**
     * @param string $classname
     * @param PropertyInfo[] $propertyArr
     * @param bool $isCreateJsonSerialize
     */
    public function __construct(
        protected string $classname,
        protected array $propertyArr,
        private bool $isCreateJsonSerialize
    ) {
        $this->factory = new BuilderFactory();
    }

    public function afterTraverse(array $nodes)
    {
        foreach ($nodes as $namespace) {
            if (! $namespace instanceof Node\Stmt\Namespace_) {
                continue;
            }

            foreach ($namespace->stmts as $class) {
                if (! $class instanceof Node\Stmt\Class_) {
                    continue;
                }
                foreach ($class->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Property) {
                        $propertyName = $stmt->props[0]->name->name;
                        $property = $this->propertyArr[$propertyName];
                        if (! empty($property->alias)) {
                            $alias = $property->alias;
                            //增加别名属性
                            $aliasStmt = clone $stmt;
                            $name = clone $stmt->props[0]->name;
                            $prop = clone $stmt->props[0];
                            $name->name = $alias;
                            $prop->name = $name;
                            $aliasStmt->props[0] = $prop;
                            $aliasStmt->flags = Node\Stmt\Class_::MODIFIER_PRIVATE;
                            $class->stmts[] = $aliasStmt;
                            //增加set方法
                            $setter = setter($alias);
                            $stmts = $this->createSetter($setter, $alias, $propertyName);

                            $class->stmts[] = $stmts;
                        }
                    }
                }
                if ($this->isCreateJsonSerialize) {
                    $stmts = $this->createJsonSerialize();
                    if ($stmts) {
                        $class->stmts[] = $stmts;
                        $class->implements[] = new Node\Name\FullyQualified('JsonSerializable');
                    }
                }
            }
        }

        return $nodes;
    }

    protected function createSetter(string $method, string $alias, string $propertyName): Node\Stmt\ClassMethod
    {
        $node = new Node\Stmt\ClassMethod($method, [
            'flags' => Node\Stmt\Class_::MODIFIER_PUBLIC,
            'params' => [new Node\Param(new Node\Expr\Variable($alias))],
        ]);
        $node->stmts[] = new Node\Stmt\Expression(
            new Node\Expr\Assign(
                new Node\Expr\PropertyFetch(
                    new Node\Expr\Variable('this'),
                    new Node\Identifier($alias)
                ),
                new Node\Expr\Variable($alias)
            )
        );
        $node->stmts[] = new Node\Stmt\Expression(
            new Node\Expr\Assign(
                new Node\Expr\PropertyFetch(
                    new Node\Expr\Variable('this'),
                    new Node\Identifier($propertyName)
                ),
                new Node\Expr\Variable($alias)
            )
        );
        $node->stmts[] = new Node\Stmt\Return_(
            new Node\Expr\Variable('this')
        );

        return $node;
    }

    protected function createJsonSerialize(): ?Node\Stmt\ClassMethod
    {
        $node = new Node\Stmt\ClassMethod('jsonSerialize', ['flags' => Node\Stmt\Class_::MODIFIER_PUBLIC]);
        $node->returnType = new Node\Identifier('array');
        $arrayItem = [];
        foreach ($this->propertyArr as $property) {
            $propertyName = $property->propertyName;
            $methodName = $property->getMethodName;
            $keyName = $property->jsonArrKey;

            if (! $property->isJsonSerialize) {
                continue;
            }

            if ($methodName) {
                //方法
                $propertyFetch = new Node\Expr\MethodCall(
                    new Node\Expr\Variable('this'),
                    new Node\Identifier($methodName)
                );
            } else {
                //$this
                $propertyFetch = new Node\Expr\PropertyFetch(
                    new Node\Expr\Variable('this'),
                    new Node\Identifier($propertyName)
                );
            }
            $key = new Node\Scalar\String_($keyName);
            $arrayItem[] = new Node\Expr\ArrayItem($propertyFetch, $key);
        }
        if (empty($arrayItem)) {
            return null;
        }

        $node->stmts[] = new Node\Stmt\Return_(
            new Node\Expr\Array_($arrayItem)
        );

        return $node;
    }
}