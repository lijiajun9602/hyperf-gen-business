<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Hyperf\GenBusiness\Visitor;

use Hyperf\CodeParser\PhpParser;
use Hyperf\Database\Commands\Ast\AbstractVisitor;
use Hyperf\Stringable\Str;
use PhpParser\Node;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use function Hyperf\Support\getter;
use function Hyperf\Support\setter;

class ModelRewriteGetterSetterVisitor extends AbstractVisitor
{
    /**
     * @var string[]
     */
    protected array $getters = [];

    /**
     * @var string[]
     */
    protected array $setters = [];

    protected array $property = [];

    public function beforeTraverse(array $nodes): void
    {
        $methods = PhpParser::getInstance()->getAllMethodsFromStmts($nodes);
        $this->collectMethods($methods);
        $this->collectProperty($this->getAllProperty($nodes));
    }

    protected function collectMethods(array $methods): void
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

    public function collectProperty(array $property): void
    {
        foreach ($property as $node) {
            if ($node === 'listSelect') {
                $this->property[] = 'listSelect';
            }
            if ($node === 'infoSelect') {
                $this->property[] = 'infoSelect';
            }
        }
    }

    public function getAllProperty(array $stmts): array
    {
        $property = [];
        foreach ($stmts as $namespace) {
            if (!$namespace instanceof Node\Stmt\Namespace_) {
                continue;
            }

            foreach ($namespace->stmts as $class) {
                if (!$class instanceof Node\Stmt\Class_ && !$class instanceof Node\Stmt\Interface_) {
                    continue;
                }

                foreach ($class->getProperties() as $method) {
                    $property[] = $method->props[0]->name->name;
                }
            }
        }
        return $property;
    }

    public function afterTraverse(array $nodes): ?array
    {
        foreach ($nodes as $namespace) {
            if (!$namespace instanceof Node\Stmt\Namespace_) {
                continue;
            }

            foreach ($namespace->stmts as $class) {
                if (!$class instanceof Node\Stmt\Class_) {
                    continue;
                }

                array_push($class->stmts, ...$this->buildSelect());
                array_push($class->stmts, ...$this->buildGetterAndSetter());
            }
        }

        return $nodes;
    }

    private function buildSelect(): array
    {
        $stmts = [];
        $data = [];
        foreach ($this->data->getColumns() as $column) {
            $data[] = new Node\Expr\ArrayItem(new Node\Scalar\String_($column['column_name']));
        }
        if (!in_array('listSelect', $this->property, true)) {
            $stmts[] = $this->createProperty("listSelect", $data);
        }
        if (!in_array('infoSelect', $this->property, true)) {
            $stmts[] = $this->createProperty("infoSelect", $data);
        }
        return $stmts;
    }

    protected function createProperty($name, $data): Property
    {
        return new Property(
            Node\Stmt\Class_::MODIFIER_PUBLIC, // 访问修饰符
            [new PropertyProperty($name, new Node\Expr\Array_($data, [
                'kind' => Node\Expr\Array_::KIND_SHORT,
            ]))], // 属性名
            $data, // 默认值
            'array'
        );
    }

    /**
     * @return Node\Stmt\ClassMethod[]
     */
    protected function buildGetterAndSetter(): array
    {
        $stmts = [];
        foreach ($this->data->getColumns() as $column) {
            $type = $this->formatPropertyType($column['data_type'], $column['cast'] ?? null);
            if (!in_array($column['column_name'], ['created_at', 'updated_at', 'deleted_at'])) {
                if ($name = $column['column_name'] ?? null) {
                    $name = $this->option->isCamelCase() ? Str::camel($name) : $name;
                    $getter = getter($name);
                    if (!in_array($getter, $this->getters, true)) {
                        $stmts[] = $this->createGetter($getter, $name, $type);
                    }
                    $setter = setter($name);
                    if (!in_array($setter, $this->setters, true)) {
                        $stmts[] = $this->createSetter($setter, $name, $type);
                    }
                }
            }
        }


        return $stmts;
    }

    protected function formatPropertyType(string $type, ?string $cast): ?string
    {
        if (!isset($cast)) {
            $cast = $this->formatDatabaseType($type) ?? 'string';
        }

        switch ($cast) {
            case 'integer':
                return 'int';
            case 'date':
            case 'datetime':
                return 'string';
            case 'json':
                return 'array';
        }

        if (Str::startsWith($cast, 'decimal')) {
            // 如果 cast 为 decimal，则 @property 改为 string
            return 'float';
        }

        return $cast;
    }

    protected function formatDatabaseType(string $type): ?string
    {
        return match ($type) {
            'tinyint', 'smallint', 'mediumint', 'int', 'bigint' => 'integer',
            'decimal' => 'decimal:2',
            'float', 'double', 'real' => 'float',
            'bool', 'boolean' => 'boolean',
            default => null,
        };
    }

    protected function createGetter(string $method, string $name, string $dataType): Node\Stmt\ClassMethod
    {
        $node = new Node\Stmt\ClassMethod($method, ['flags' => Node\Stmt\Class_::MODIFIER_PUBLIC, 'returnType' => $dataType]);
        $node->stmts[] = new Node\Stmt\Return_(
            new Node\Expr\PropertyFetch(
                new Node\Expr\Variable('this'),
                new Node\Identifier($name),
            )
        );

        return $node;
    }

    protected function createSetter(string $method, string $name, string $dataType): Node\Stmt\ClassMethod
    {
        $node = new Node\Stmt\ClassMethod($method, [
            'flags' => Node\Stmt\Class_::MODIFIER_PUBLIC,
            'params' => [new Node\Param(new Node\Expr\Variable($name), null, $dataType)],
            'returnType' => 'static'
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

    public function leaveNode(Node $node): Node|PropertyProperty|null
    {
        if ($node instanceof PropertyProperty) {
            if ((string)$node->name === 'listSelect') {
                $node = $this->rewrite($node);
            } elseif ((string)$node->name === 'infoSelect') {
                $node = $this->rewrite($node);
            }
            return $node;
        }

        return null;
    }

    private function rewrite(PropertyProperty $node): PropertyProperty
    {
        $data = [];
        foreach ($this->data->getColumns() as $column) {
            $data[] = new Node\Expr\ArrayItem(new Node\Scalar\String_($column['column_name']));
        }
        $node->default = new Node\Expr\Array_($data, [
            'kind' => Node\Expr\Array_::KIND_SHORT,
        ]);
        return $node;
    }


}
