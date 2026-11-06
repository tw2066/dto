<?php

declare(strict_types=1);

namespace Hyperf\DTO\Ast;

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

    protected string $classShortName;

    /** @var Node\Stmt\Class_[] */
    private array $classStack = [];

    /**
     * @param PropertyInfo[] $propertyArr
     */
    public function __construct(
        protected string $classname,
        protected array $propertyArr,
        protected bool $isCreateJsonSerialize,
        protected int $dtoDefaultValueLevel,
    ) {
        $separator = strrpos($classname, '\\');
        $this->classShortName = $separator === false ? $classname : substr($classname, $separator + 1);
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_) {
            $this->classStack[] = $node;
        }
        return null;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_) {
            array_pop($this->classStack);
            return null;
        }

        $currentClass = end($this->classStack);
        if (! $currentClass instanceof Node\Stmt\Class_ || ! $this->isTargetClass($currentClass)) {
            return null;
        }

        if ($node instanceof Node\Stmt\Property) {
            foreach ($node->props as $prop) {
                if (! empty($prop->default)) {
                    continue;
                }
                $default = $this->createDefaultValue($node, $prop->name->name, ! $node->isReadonly());
                // readonly 声明属性不能携带默认值，但仍需保留序列化元数据。
                if ($this->dtoDefaultValueLevel > 0 && ! $node->isReadonly() && $default !== null) {
                    $prop->default = $default;
                }
            }
            return null;
        }

        // 带可见性修饰符的构造器参数即 promoted 属性。
        if ($node instanceof Node\Param && $node->flags !== 0 && $node->var instanceof Node\Expr\Variable
            && is_string($node->var->name) && empty($node->default)) {
            $default = $this->createDefaultValue($node, $node->var->name, true);
            if ($this->dtoDefaultValueLevel > 0 && $default !== null) {
                $node->default = $default;
            }
        }
        return null;
    }

    private function createDefaultValue(Node\Stmt\Property|Node\Param $node, string $fieldName, bool $canInjectDefault): ?Expr
    {
        $type = $node->type;
        // 简单类型
        if ($type instanceof Node\Identifier && array_key_exists($dataTypeKey = $type->name, $this->dataTypeDefaultValue)) {
            $value = $this->dataTypeDefaultValue[$dataTypeKey];
            $this->dtoDefaultValueLevel == 0 && $this->jsonSerializeNotDefaultValue[$fieldName] = $dataTypeKey;
            return $this->normalizeValue($value);
        }
        if ($type instanceof Node\NullableType) {
            $this->dtoDefaultValueLevel == 0 && $this->jsonSerializeNotDefaultValue[$fieldName] = null;
            return new Node\Expr\ConstFetch(new Node\Name(['null']));
        }
        if ($type instanceof Node\Name || $type instanceof Node\Identifier) {
            if ($this->dtoDefaultValueLevel == 2 && $canInjectDefault) {
                $node->type = new Node\NullableType($type);
                return new Node\Expr\ConstFetch(new Node\Name(['null']));
            }
            $this->jsonSerializeNotDefaultValue[$fieldName] = null;
        }
        return null;
    }

    public function afterTraverse(array $nodes)
    {
        foreach ($nodes as $namespace) {
            if (! $namespace instanceof Node\Stmt\Namespace_) {
                continue;
            }

            foreach ($namespace->stmts as $class) {
                if (! $class instanceof Node\Stmt\Class_ || ! $this->isTargetClass($class)) {
                    continue;
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

    private function isTargetClass(Node\Stmt\Class_ $class): bool
    {
        return $class->name?->toString() === $this->classShortName;
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
                // 设置默认值
                $dataTypeKey = $this->jsonSerializeNotDefaultValue[$propertyName] ?? null;
                $value = $this->dataTypeDefaultValue[$dataTypeKey] ?? null;
                $default = $this->normalizeValue($value);
                $propertyFetch = new Node\Expr\BinaryOp\Coalesce(
                    $propertyFetch,
                    $default
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
