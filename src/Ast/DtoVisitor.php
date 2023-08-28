<?php

declare(strict_types=1);

namespace Hyperf\DTO\Ast;

use Hyperf\DTO\DtoConfig;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;

class DtoVisitor extends NodeVisitorAbstract
{
    public array $jsonSerializeNotDefaultValue = [];

    protected array $dataTypeDefaultValue = [
        'int' => 0,
        'float' => 0,
        'string' => '',
        'array' => [],
        'bool' => false,
        'mixed' => null,
    ];

    /**
     * @param PropertyInfo[] $propertyArr
     */
    public function __construct(
        protected string $classname,
        protected array $propertyArr,
        protected bool $isCreateJsonSerialize,
        protected int $dtoDefaultValueLevel,
    ) {
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Property) {
            $prop = $node->props[0];
            $fieldName = $prop->name->name;
            $type = $node->type;
            if (empty($prop->default)) {
                // 简单类型
                if ($type instanceof Node\Identifier && array_key_exists($dataTypeKey = $type->name, $this->dataTypeDefaultValue)) {
                    $value = $this->dataTypeDefaultValue[$dataTypeKey];
                    $default = $this->normalizeValue($value);
                    $this->dtoDefaultValueLevel == 0 && $this->jsonSerializeNotDefaultValue[$fieldName] = $dataTypeKey;
                } elseif ($type instanceof Node\NullableType) {
                    $default = new Node\Expr\ConstFetch(
                        new Node\Name(['null'])
                    );
                    $this->dtoDefaultValueLevel == 0 && $this->jsonSerializeNotDefaultValue[$fieldName] = null;
                } elseif ($type instanceof Node\Name || $type instanceof Node\Identifier) {
                    if ($this->dtoDefaultValueLevel == 2) {
                        $node->type = new Node\NullableType($type);
                        $default = new Node\Expr\ConstFetch(
                            new Node\Name(['null'])
                        );
                    } else {
                        $this->jsonSerializeNotDefaultValue[$fieldName] = null;
                    }
                }
            }

            // 设置变量默认值
            if ($this->dtoDefaultValueLevel > 0 && ! empty($default)) {
                $node->props[0]->default = $default;
            }
        }
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
                            // 增加别名属性
                            $aliasStmt = clone $stmt;
                            $name = clone $stmt->props[0]->name;
                            $prop = clone $stmt->props[0];
                            $name->name = $alias;
                            $prop->name = $name;
                            $aliasStmt->props[0] = $prop;
                            $aliasStmt->flags = Node\Stmt\Class_::MODIFIER_PRIVATE;
                            $class->stmts[] = $aliasStmt;
                            // 增加set属性方法
                            $setter = DtoConfig::getDtoAliasMethodName($alias);
                            $stmts = $this->createSetter($setter, $alias, $propertyName, $stmt->props[0]->default, $stmt->type);
                            // 删除原有注解
                            // $stmt->attrGroups = [];
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

    public function normalizeValue($value): Expr
    {
        if ($value instanceof Node\Expr) {
            return $value;
        }

        if (is_null($value)) {
            return new Expr\ConstFetch(
                new Name('null')
            );
        }

        if (is_bool($value)) {
            return new Expr\ConstFetch(
                new Name($value ? 'true' : 'false')
            );
        }

        if (is_int($value)) {
            return new Node\Scalar\LNumber($value);
        }

        if (is_float($value)) {
            return new Node\Scalar\DNumber($value);
        }

        if (is_string($value)) {
            return new Node\Scalar\String_($value);
        }

        if (is_array($value)) {
            $items = [];
            $lastKey = -1;
            foreach ($value as $itemKey => $itemValue) {
                // for consecutive, numeric keys don't generate keys
                if ($lastKey !== null && ++$lastKey === $itemKey) {
                    $items[] = new Expr\ArrayItem(
                        self::normalizeValue($itemValue)
                    );
                } else {
                    $lastKey = null;
                    $items[] = new Expr\ArrayItem(
                        self::normalizeValue($itemValue),
                        self::normalizeValue($itemKey)
                    );
                }
            }

            return new Expr\Array_($items);
        }

        return new Expr\ConstFetch(
            new Name('null')
        );
    }

    protected function createSetter(string $method, string $alias, string $propertyName, $default, $type): Node\Stmt\ClassMethod
    {
        $node = new Node\Stmt\ClassMethod($method, [
            'flags' => Node\Stmt\Class_::MODIFIER_PRIVATE,
            'params' => [new Node\Param(new Node\Expr\Variable($alias), $default, $type)],
        ]);
        $node->returnType = new Node\Identifier('void');
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
                // 方法
                $propertyFetch = new Node\Expr\MethodCall(
                    new Node\Expr\Variable('this'),
                    new Node\Identifier($methodName)
                );
            } else {
                // $this
                $propertyFetch = new Node\Expr\PropertyFetch(
                    new Node\Expr\Variable('this'),
                    new Node\Identifier($propertyName)
                );
                // 未设置默认值
                if (array_key_exists($propertyName, $this->jsonSerializeNotDefaultValue)) {
                    $dataTypeKey = $this->jsonSerializeNotDefaultValue[$propertyName] ?? null;
                    $value = $this->dataTypeDefaultValue[$dataTypeKey] ?? null;
                    $default = $this->normalizeValue($value);
                    $propertyFetch = new Node\Expr\BinaryOp\Coalesce(
                        $propertyFetch,
                        $default
                    );
                }
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
