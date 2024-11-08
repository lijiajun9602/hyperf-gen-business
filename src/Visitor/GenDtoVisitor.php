<?php /** @noinspection PhpParamsInspection */

namespace Hyperf\GenBusiness\Visitor;

use Hyperf\ApiDocs\Annotation\ApiModel;
use Hyperf\ApiDocs\Annotation\ApiModelProperty;
use Hyperf\GenBusiness\Common\Dto\PageClass;
use Hyperf\GenBusiness\Common\Dto\UserJwtAuthIn;
use Hyperf\GenBusiness\Common\Enums\NewEnumCodeGet;
use Hyperf\GenBusiness\Common\Util\CommonUtil;
use Hyperf\CodeParser\Project;
use Hyperf\Database\Commands\Ast\AbstractVisitor;
use Hyperf\Database\Model\Model;
use Hyperf\DTO\Annotation\Dto;
use Hyperf\DTO\Annotation\Validation\In;
use Hyperf\DTO\Annotation\Validation\Required;
use Hyperf\PhpAccessor\Annotation\HyperfData;
use Hyperf\Stringable\Str;
use Lishun\Enums\Annotations\EnumCode;
use Lishun\Enums\Interfaces\EnumCodeInterface;
use PhpAccessor\Attribute\Data;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\DeclareDeclare;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\Node\VarLikeIdentifier;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use RuntimeException;
use function Hyperf\Collection\collect;
use function Hyperf\Config\config;
use function Hyperf\Support\setter;

class GenDtoVisitor extends AbstractVisitor
{
    protected array $attribute = [];

    protected array $methods = [];

    protected Model $class;

    protected array $items = [];


    public function beforeTraverse(array $nodes): void
    {
        $class = explode("\\", $this->data->getClass());
        $path = config('gen-business.generator.DtoOut.namespace', 'app/Controller/Dto');
        $dtoOutPath = $path . "/" . $class[count($class) - 1];
        $inPath = $dtoOutPath . "/In";
        $outPath = $dtoOutPath . "/Out";
        $this->mkdir(BASE_PATH. '/' . $dtoOutPath, 1);
        $this->mkdir(BASE_PATH. '/' . $inPath, 1);
        $this->mkdir(BASE_PATH. '/' . $outPath, 1);
        $this->collectEnums($class);
        $this->collectClassOut($outPath, $class, "ListDtoOut");
        $this->collectClassOut($outPath, $class, "InfoDtoOut");
        $this->collectClassIn($inPath, $class, "CreateDtoIn");
        $this->collectClassIn($inPath, $class, "UpdateDtoIn");
        $this->collectClassIn($inPath, $class, "ByIdDtoIn");
        $this->collectClassIn($inPath, $class, "PageDtoIn");

    }

    private function collectEnums(array $class): void
    {
        $path = config('gen-business.generator.Enums.namespace', 'app/Enums');
        $className = $class[count($class) - 1];
        [$namespace, $classPath, $isMkdir] = CommonUtil::mkdirClass($className, "Enum", $path);
        $code = $this->getPrettyPrintEnumFile($className . "Enum", Str::rtrim($namespace, "\\"), $className, $isMkdir, $classPath);
        file_put_contents($classPath, $code);
    }


    public function getStmt($node): void
    {
        $items = [];
        foreach ($node->stmts as $value) {
            if ($value instanceof Enum_) {
                foreach ($value->stmts as $stmts) {
                    if ($stmts instanceof EnumCase) {
                        $items[] = $stmts->name->name;
                    }
                }
            }
            if ($value instanceof Class_) {
                foreach ($value->stmts as $stmts) {
                    if ($stmts instanceof Property) {
                        $items[] = $stmts->props[0]->name->name;
                    }
                }
            }

        }
        $this->items = $items;
    }

    private function collectClassIn(string $path, array $class, string $outName): void
    {
        [$namespace, $classPath, $isMkdir] = CommonUtil::mkdirClass($class[count($class) - 1], $outName, $path);
        $code = $this->getPrettyPrintInFile($class[count($class) - 1] . $outName, Str::rtrim($namespace, "\\"), $outName, $classPath, $isMkdir);
        file_put_contents($classPath, $code);
    }

    protected function collectClassOut(mixed $path, array $class, string $outName): void
    {
        [$namespace, $classPath, $isMkdir] = CommonUtil::mkdirClass($class[count($class) - 1], $outName, $path);
        $code = $this->getPrettyPrintFile($class[count($class) - 1] . $outName, Str::rtrim($namespace, "\\"), $classPath, $isMkdir);
        file_put_contents($classPath, $code);
    }

    protected function mkdir(string $path, $type = 0): void
    {
        if ($type === 1) {
            $dir = $path;
        } else {
            $dir = dirname($path);
        }

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }
    }


    public function getPrettyPrintInFile($class, $namespace, $outName, $classPath, $isMkdir): string
    {
        if ($isMkdir) {
            $declareStrictTypes = new Declare_(
                [new DeclareDeclare(
                    "strict_types",
                    new LNumber(1)
                )]
            );
            $node = new Class_($class);
            // 创建一个代码美化器
            $prettyPrinter = new Standard();
            [$stmts, $enumIf] = $this->buildInProperty($outName);
            $namespace = new Namespace_(new Name($namespace));
            $classUses = [];
            $uses[] = new Use_([new UseUse(new Name(Dto::class))]);
            $uses[] = new Use_([new UseUse(new Name(Data::class))]);
            $uses[] = new Use_([new UseUse(new Name(HyperfData::class))]);
            $uses[] = new Use_([new UseUse(new Name(ApiModel::class))]);
            if ($outName === 'PageDtoIn') {
                $uses[] = new Use_([new UseUse(new Name(PageClass::class))]);
                $stmts = [];
                $enumIf = false;
                $classUses[] = new Use_([new UseUse(new Name("PageClass"))]);
                array_push($node->stmts, ...$classUses);
            } else {
                $uses[] = new Use_([new UseUse(new Name(Required::class))]);
                $uses[] = new Use_([new UseUse(new Name(ApiModelProperty::class))]);
            }
            if ($enumIf) {
                $uses[] = new Use_([new UseUse(new Name(In::class))]);
            }
            $uses[] = new Use_([new UseUse(new Name(UserJwtAuthIn::class))]);
            $node->extends = new Name("UserJwtAuthIn");

            array_push($namespace->stmts, ...$uses);
            $attribute = new Attribute(new Name("Dto"));
            $attributeGroup[] = new AttributeGroup([$attribute]);
            $attribute = new Attribute(new Name("Data"));
            $attributeGroup[] = new AttributeGroup([$attribute]);
            $attribute = new Attribute(new Name("HyperfData"));
            $attributeGroup[] = new AttributeGroup([$attribute]);
            $arg = new Arg(new String_($class . "入参"));
            $arg->name = new Identifier("value");
            $attribute = new Attribute(new Name("ApiModel"), [$arg]);
            $attributeGroup[] = new AttributeGroup([$attribute]);
            $node->attrGroups = $attributeGroup;

            array_push($node->stmts, ...$stmts);
            return $prettyPrinter->prettyPrintFile([$declareStrictTypes,$namespace, $node]);
        }
        $code = file_get_contents($classPath);
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $node = $parser->parse($code);
        $this->getStmt($node[0]);
        [$stmts] = $this->buildInProperty($outName);
        foreach ($node[0]->stmts as $key => $stmt) {
            if ($stmt instanceof Class_) {
                array_push($node[0]->stmts[$key]->stmts, ...$stmts);
            }
        }
        $this->items = [];
        return (new Standard())->prettyPrintFile($node);
    }

    private function getPrettyPrintEnumFile($class, $namespace, $className, $isMkdir, $classPath): string
    {
        if ($isMkdir) {
            $node = new Enum_($class, [
                'scalarType' => 'string',
            ]);
            $node->implements = [new Name("EnumCodeInterface")];

            $namespace = new Namespace_(new Name($namespace));
            $uses[] = new Use_([new UseUse(new Name(EnumCodeInterface::class))]);
            $uses[] = new Use_([new UseUse(new Name(NewEnumCodeGet::class))]);
            $uses[] = new Use_([new UseUse(new Name(EnumCode::class))]);
            array_push($namespace->stmts, ...$uses);
            $nodeUses[] = new Use_([new UseUse(new Name('NewEnumCodeGet'))]);
            array_push($node->stmts, ...$nodeUses);
            $stmts = $this->buildEnumProperty($className);
            array_push($node->stmts, ...$stmts);
            return (new Standard())->prettyPrintFile([$namespace, $node]);
        }

        $code = file_get_contents($classPath);
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $node = $parser->parse($code);
        $this->getStmt($node[0]);

        $stmts = $this->buildEnumProperty($className);
        foreach ($node[0]->stmts as $key => $stmt) {
            if ($stmt instanceof Enum_) {
                array_push($node[0]->stmts[$key]->stmts, ...$stmts);
            }
        }
        $this->items = [];
        return (new Standard())->prettyPrintFile($node);
    }

    /**
     * @param $class
     * @param $namespace
     * @param $classPath
     * @param $isMkdir
     * @return string
     */
    public function getPrettyPrintFile($class, $namespace, $classPath, $isMkdir): string
    {
        if ($isMkdir) {
            $declareStrictTypes = new Declare_(
                [new DeclareDeclare(
                    "strict_types",
                    new LNumber(1)
                )]
            );
            $node = new Class_($class);

            // 创建一个代码美化器
            $prettyPrinter = new Standard();
            $namespace = new Namespace_(new Name($namespace));
            $uses[] = new Use_([new UseUse(new Name(Dto::class))]);
            $uses[] = new Use_([new UseUse(new Name(ApiModelProperty::class))]);

            $path = Str::rtrim(config('gen-business.generator.Enums.namespace', 'app/Enums'), "\\");
            $class = explode("\\", $this->data->getClass());
            $className = $class[count($class) - 1];
            $project = new Project();
            $path = $project->namespace($path);
            $uses[] = new Use_([new UseUse(new Name($path . $className . "Enum"))]);
            array_push($namespace->stmts, ...$uses);
            $attribute = new Attribute(new Name("Dto"));
            $attributeGroup = new AttributeGroup([$attribute]);
            $node->attrGroups = [$attributeGroup];
            array_push($node->stmts, ...$this->buildProperty());
            return $prettyPrinter->prettyPrintFile([$declareStrictTypes,$namespace, $node]);
        }
        $code = file_get_contents($classPath);
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $node = $parser->parse($code);
        $this->getStmt($node[0]);
        $stmts = $this->buildProperty();
        foreach ($node[0]->stmts as $key => $stmt) {
            if ($stmt instanceof Class_) {
                array_push($node[0]->stmts[$key]->stmts, ...$stmts);
            }
        }
        $this->items = [];
        return (new Standard())->prettyPrintFile($node);
    }

    private function buildInProperty($outName): array
    {
        $enumIf = false;
        $stmts = [];
        $array = ['created_at', 'updated_at', 'deleted_at', 'lock'];
        // array_push($array,...$notIn);
        $columns = collect($this->data->getColumns())->whereNotIn('column_name', $array)->toArray();
        foreach ($columns as $column) {
            $name = $this->option->isCamelCase() ? Str::camel($column['column_name']) : $column['column_name'];
            $type = $this->formatPropertyType($column['data_type'], $column['cast'] ?? null);
            if ($outName === 'CreateDtoIn' && !empty($column['column_key'])) {
                continue;
            }
            if ($outName === 'ByIdDtoIn' && empty($column['column_key'])) {
                continue;
            }
            if ($outName === 'PageDtoIn') {
                continue;
            }

            if ($column['data_type'] === "enum") {
                $enumIf = true;
            }
            $stmt = $this->createInProperty($name, $type, $column);
            if ($stmt !== null) {
                $stmts[] = $stmt;
            }
        }
        return [$stmts, $enumIf];
    }


    protected function buildProperty(): array
    {
        $stmts = $methods = [];
        foreach ($this->data->getColumns() as $column) {
            $name = $this->option->isCamelCase() ? Str::camel($column['column_name']) : $column['column_name'];
            $type = $this->formatPropertyType($column['data_type'], $column['cast'] ?? null);
            $comment = $this->option->isWithComments() ? $column['column_comment'] ?? '' : '';
            if ($column['data_type'] === 'enum') {
                $stmt = $this->createProperty($name . "Name", "string", $comment . "中文标识");
                if ($stmt !== null) {
                    $type = $this->formatPropertyType($column['data_type'], $column['cast'] ?? null);
                    $setter = setter($name);
                    $methods[] = $this->createSetter($setter, $name, $type);
                    $stmts[] = $stmt;
                }
            }
            $stmt = $this->createProperty($name, $type, $comment);
            if ($stmt !== null) {
                $stmts[] = $stmt;
            }
        }
        $this->methods = $methods;
        array_push($stmts, ...$methods);
        return $stmts;
    }

    protected function createSetter(string $method, string $name, string $dataType): ClassMethod
    {
        $node = new ClassMethod($method, [
            'flags' => Class_::MODIFIER_PUBLIC,
            'params' => [new Param(new Variable($name), null, $dataType)],
            'returnType' => 'static'
        ]);
        $node->stmts[] = new Expression(
            new Assign(
                new PropertyFetch(
                    new Variable('this'),
                    new Identifier($name)
                ),
                new Variable($name)
            )
        );


        $class = explode("\\", $this->data->getClass());
        $className = $class[count($class) - 1];


        $node->stmts[] = new Expression(
            new Assign(
                new PropertyFetch(
                    new Variable('this'),
                    new Identifier($name . "Name")
                ),
                new MethodCall(
                    new ClassConstFetch(
                        new Name($className . "Enum"), // 调用方法的对象,
                        new VarLikeIdentifier(Str::upper($this->fieldToUnderscore($name))),
                    ),
                    new Identifier("getExt"), // 被调用的方法名
                    [
                        new Variable($name)
                    ]
                ),

            )
        );
        $node->stmts[] = new Return_(
            new Variable('this')
        );

        return $node;
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

    protected function createProperty($name, $type, $comment): Property|null
    {
        if (!in_array($name, $this->items, true)) {
            $pro = new Property(
                Class_::MODIFIER_PUBLIC, // 访问修饰符
                [new PropertyProperty($name)], // 属性名
                [],
                "?" . $type
            );
            $arg = new Arg(new String_($comment));
            $arg->name = new Identifier("value");
            $attribute = new Attribute(new Name("ApiModelProperty"), [$arg]);
            $attributeGroup = new AttributeGroup([$attribute]);
            $pro->attrGroups = [$attributeGroup];
            return $pro;
        }
        return null;
    }

    private function buildEnumProperty($className): array
    {
        $cases = [];
        if (!in_array('CREATE_LOCK_KEY', $this->items, true)) {
            $cases[] = $this->getEnumCase('CREATE_LOCK_KEY', new String_($this->fieldToUnderscore($className) . ':create_lock:'), "创建锁");
        }
        if (!in_array('UPDATE_LOCK_KEY', $this->items, true)) {
            $cases[] = $this->getEnumCase('UPDATE_LOCK_KEY', new String_($this->fieldToUnderscore($className) . ':update_lock:'), "编辑锁");
        }
        if (!in_array('DELETE_LOCK_KEY', $this->items, true)) {
            $cases[] = $this->getEnumCase('DELETE_LOCK_KEY', new String_($this->fieldToUnderscore($className) . ':delete_lock:'), "删除锁");
        }
        array_push($cases, ...$this->getColumnBuildEnumProperty());
        return $cases;
    }

    public function fieldToUnderscore($field): string
    {
        $pattern = array('/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/');
        $replace = array('\\1_\\2', '\\1_\\2');
        $result = preg_replace($pattern, $replace, $field);
        if ($result) {
            $result = strtolower($result);
        }
        return $result;
    }

    protected function createInProperty($name, $type, $column): Property|null
    {
        if (!in_array($name, $this->items, true)) {
            $pro = new Property(
                Class_::MODIFIER_PUBLIC, // 访问修饰符
                [new PropertyProperty($name)], // 属性名
                [],
                "?" . $type
            );
            $arg = new Arg(new String_($column['column_comment']));
            $arg->name = new Identifier("value");
            $attribute = new Attribute(new Name("ApiModelProperty"), [$arg]);
            $attributeGroup[] = new AttributeGroup([$attribute]);
            $comment = explode(":", $column['column_comment'])[0] ?? $column['column_comment'];
            $arg = new Arg(new String_($comment . "不能为空"));
            $arg->name = new Identifier("messages");
            $attribute = new Attribute(new Name("Required"), [$arg]);
            $attributeGroup[] = new AttributeGroup([$attribute]);
            if ($column['data_type'] === "enum") {
                $arg = new Arg(new String_($comment . "格式错误"));
                $arg->name = new Identifier("messages");
                $enumValues = collect(explode("','", substr($column['column_type'], 6, -2)))->map(function ($item) {
                    return new ArrayItem(new String_($item));
                });
                $arg1 = new Arg(new Array_($enumValues->toArray(),
                    [
                        'kind' => Array_::KIND_SHORT,
                    ]));
                $arg1->name = new Identifier("value");
                $attribute = new Attribute(new Name("In"), [$arg1, $arg]);
                $attributeGroup[] = new AttributeGroup([$attribute]);
            }
            $pro->attrGroups = $attributeGroup;
            return $pro;
        }
        return null;
    }


    private function getColumnBuildEnumProperty(): array
    {
        $columns = collect($this->data->getColumns())->where('data_type', "enum")->toArray();
        $data = [];
        foreach ($columns as $column) {
            if (!in_array(Str::upper($column['column_name']), $this->items, true)) {
                $commentArray = explode(":", $column['column_comment']);
                $comment = $commentArray[0] ?? $column['column_comment'];
                $list = explode(",", $commentArray[1]);
                $ext = [];
                if ($list) {
                    foreach ($list as $item) {
                        $valueList = explode("-", $item);
                        if (!empty($valueList)) {
                            $name = $valueList[0];
                            $name = $column['column_name'] . "_" . $name;
                            if (!in_array(Str::upper($name), $this->items, true)) {
                                $data[] = $this->getEnumCase(Str::upper($name), new String_($valueList[1]), $valueList[0]);
                            }
                            $ext[] = new ArrayItem(new String_($valueList[1]), new String_($valueList[0]));
                        }
                    }
                }
                $data[] = $this->getEnumCase(Str::upper($column['column_name']), new String_($column['column_name']), $comment, $ext);
            }
        }
        return $data;
    }

    /**
     * @param $caseName
     * @param $caseValue
     * @param $msg
     * @param array $ext
     * @return EnumCase
     */
    public function getEnumCase($caseName, $caseValue, $msg, array $ext = []): EnumCase
    {
        $case = new EnumCase($caseName, $caseValue);
        $arg = new Arg(new String_($msg));
        $arg->name = new Identifier("msg");
        $args[] = $arg;
        if (!empty($ext)) {
            $arg = new Arg(new Array_($ext,
                [
                    'kind' => Array_::KIND_SHORT,
                ]));
            $arg->name = new Identifier("ext");
            $args[] = $arg;
        }
        $attribute = new Attribute(new Name("EnumCode"), $args);
        $attributeGroup = new AttributeGroup([$attribute]);
        $case->attrGroups = [$attributeGroup];
        return $case;
    }


}