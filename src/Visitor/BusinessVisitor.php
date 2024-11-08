<?php /** @noinspection CallableParameterUseCaseInTypeContextInspection */

namespace Hyperf\GenBusiness\Visitor;

use Hyperf\ApiDocs\Annotation\Api;
use Hyperf\ApiDocs\Annotation\ApiOperation;
use Hyperf\ApiDocs\Annotation\ApiResponse;
use Hyperf\GenBusiness\Common\Controller\AbstractController;
use Hyperf\GenBusiness\Common\Dto\ResponseClass;
use Hyperf\GenBusiness\Common\Exception\AppBadRequestException;
use Hyperf\GenBusiness\Common\Lock\RedisLock;
use Hyperf\GenBusiness\Common\Util\CommonUtil;
use Hyperf\CodeParser\Project;
use Hyperf\Contract\LengthAwarePaginatorInterface;
use Hyperf\Database\Commands\Ast\AbstractVisitor;
use Hyperf\DbConnection\Annotation\Transactional;
use Hyperf\Di\Annotation\Inject;
use Hyperf\DTO\Annotation\Contracts\RequestBody;
use Hyperf\DTO\Annotation\Contracts\Valid;
use Hyperf\DTO\Mapper;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\Redis\Redis;
use Hyperf\Stringable\Str;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
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
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\Node\VarLikeIdentifier;
use PhpParser\PrettyPrinter\Standard;
use function Hyperf\Config\config;

class BusinessVisitor extends AbstractVisitor
{

    protected string $className;

    protected string $classComment;

    public function beforeTraverse(array $nodes): void
    {
        $class = $this->data->getClass();
        $this->classComment = CommonUtil::getTableComment((new $class)->getTable());
        $class = explode("\\", $class);
        $this->className = $class[count($class) - 1];
        $this->collectMapper();
        $this->collectService();
        $this->collectController();
    }



    private function collectMapper(): void
    {
        $path = config('gen-business.generator.BusinessMappers.namespace', 'app/Mapper');
        [$namespace, $classPath, $isMkdir] = CommonUtil::mkdirClass($this->className, "Mapper", $path);
        $code = $this->getPrettyPrintMapperFile($this->className . "Mapper", Str::rtrim($namespace, "\\"), $this->className, $isMkdir, $classPath);
        file_put_contents($classPath, $code);
    }

    private function collectService(): void
    {
        $path = config('gen-business.generator.BusinessServices.namespace', 'app/Service');
        [$namespace, $classPath, $isMkdir] = CommonUtil::mkdirClass($this->className, "Service", $path);
        $code = $this->getPrettyPrintServiceFile($this->className . "Service", Str::rtrim($namespace, "\\"), $this->className, $isMkdir, $classPath);
        file_put_contents($classPath, $code);
    }

    private function collectController(): void
    {
        $path = config('gen-business.generator.BusinessControllers.namespace', 'app/Controller');
        [$namespace, $classPath, $isMkdir] = CommonUtil::mkdirClass($this->className, "Controller", $path);
        $code = $this->getPrettyPrintControllerFile($this->className . "Controller", Str::rtrim($namespace, "\\"), $this->className, $isMkdir, $classPath);
        file_put_contents($classPath, $code);
    }



    private function getPrettyPrintMapperFile(string $class, string $namespace, mixed $isMkdir)
    {
        $stmt = [];
        if ($isMkdir) {
            $declareStrictTypes = new Declare_(
                [new DeclareDeclare(
                    "strict_types",
                    new LNumber(1)
                )]
            );
            $path = config('gen-business.generator.DtoOut.namespace', 'app/Controller/Dto');
            $project = new Project();
            $namespaceIn = $project->namespace($path) . $this->className . "\In\\";
            $node = new Class_($class);
            $namespace = new Namespace_(new Name($namespace));
            $uses[] = new Use_([new UseUse(new Name("App\Model\\" . $this->className))]);
            $uses[] = new Use_([new UseUse(new Name($namespaceIn . $this->className . "CreateDtoIn"))]);
            $uses[] = new Use_([new UseUse(new Name($namespaceIn . $this->className . "PageDtoIn"))]);
            $uses[] = new Use_([new UseUse(new Name($namespaceIn . $this->className . "UpdateDtoIn"))]);
            $uses[] = new Use_([new UseUse(new Name(Inject::class))]);
            $uses[] = new Use_([new UseUse(new Name(Mapper::class))]);
            $uses[] = new Use_([new UseUse(new Name(LengthAwarePaginatorInterface::class))]);
            array_push($namespace->stmts, ...$uses);
            $stmts[] = $this->buildProperty();
            $methods = $this->buildMethods();
            array_push($stmts, ...$methods);
            array_push($node->stmts, ...$stmts);
            return (new Standard())->prettyPrintFile([$declareStrictTypes, $namespace, $node]);
        }
    }

    private function getPrettyPrintServiceFile(string $class, string $namespace, mixed $isMkdir)
    {
        $stmt = [];
        if ($isMkdir) {
            $declareStrictTypes = new Declare_(
                [new DeclareDeclare(
                    "strict_types",
                    new LNumber(1)
                )]
            );
            $path = config('gen-business.generator.DtoOut.namespace', 'app/Controller/Dto');
            $project = new Project();
            $namespaceIn = $project->namespace($path) . $this->className . "\In\\";
            $pathMapper = config('gen-business.generator.BusinessMappers.namespace', 'app/Mapper');
            $namespaceMapper = $project->namespace($pathMapper);
            $node = new Class_($class);
            $namespace = new Namespace_(new Name($namespace));
            $pathEnum = Str::rtrim(config('gen-business.generator.Enums.namespace', 'app/Enums'), "\\");
            $namespaceEnum = $project->namespace($pathEnum);
            $uses[] = new Use_([new UseUse(new Name($namespaceEnum . $this->className . "Enum"))]);
            $uses[] = new Use_([new UseUse(new Name($namespaceMapper . $this->className . "Mapper"))]);
            $uses[] = new Use_([new UseUse(new Name("App\Model\\" . $this->className))]);
            $uses[] = new Use_([new UseUse(new Name($namespaceIn . $this->className . "CreateDtoIn"))]);
            $uses[] = new Use_([new UseUse(new Name($namespaceIn . $this->className . "PageDtoIn"))]);
            $uses[] = new Use_([new UseUse(new Name($namespaceIn . $this->className . "UpdateDtoIn"))]);
            $uses[] = new Use_([new UseUse(new Name(Inject::class))]);
            $uses[] = new Use_([new UseUse(new Name(LengthAwarePaginatorInterface::class))]);
            $uses[] = new Use_([new UseUse(new Name(AppBadRequestException::class))]);
            $uses[] = new Use_([new UseUse(new Name(Redis::class))]);
            $uses[] = new Use_([new UseUse(new Name(RedisLock::class))]);
            $uses[] = new Use_([new UseUse(new Name(Transactional::class))]);
            array_push($namespace->stmts, ...$uses);
            $stmts = $this->buildServiceProperty();
            $methods = $this->buildServiceMethods();
            array_push($stmts, ...$methods);
            array_push($node->stmts, ...$stmts);
            return (new Standard())->prettyPrintFile([$declareStrictTypes, $namespace, $node]);
        }
    }

    private function getPrettyPrintControllerFile(string $class, string $namespace, mixed $isMkdir)
    {
        $stmt = [];
        if ($isMkdir) {
            $declareStrictTypes = new Declare_(
                [new DeclareDeclare(
                    "strict_types",
                    new LNumber(1)
                )]
            );
            $path = config('gen-business.generator.DtoOut.namespace', 'app/Controller/Dto');
            $project = new Project();
            $namespaceIn = $project->namespace($path) . $this->className . "\In\\";
            $namespaceOut = $project->namespace($path) . $this->className . "\Out\\";
            $pathService = config('gen-business.generator.BusinessServices.namespace', 'app/Mapper');
            $namespaceService = $project->namespace($pathService);
            $node = new Class_($class);
            $namespace = new Namespace_(new Name($namespace));
            $uses[] = new Use_([new UseUse(new Name($namespaceService . $this->className . "Service"))]);
            $uses[] = new Use_([new UseUse(new Name("App\Model\\" . $this->className))]);
            $uses[] = new Use_([new UseUse(new Name($namespaceIn . $this->className . "ByIdDtoIn"))]);
            $uses[] = new Use_([new UseUse(new Name($namespaceIn . $this->className . "CreateDtoIn"))]);
            $uses[] = new Use_([new UseUse(new Name($namespaceIn . $this->className . "PageDtoIn"))]);
            $uses[] = new Use_([new UseUse(new Name($namespaceIn . $this->className . "UpdateDtoIn"))]);
            $uses[] = new Use_([new UseUse(new Name($namespaceOut . $this->className . "InfoDtoOut"))]);
            $uses[] = new Use_([new UseUse(new Name($namespaceOut . $this->className . "ListDtoOut"))]);
            $uses[] = new Use_([new UseUse(new Name(Inject::class))]);
            $uses[] = new Use_([new UseUse(new Name(ResponseClass::class))]);
            $uses[] = new Use_([new UseUse(new Name(Controller::class))]);
            $uses[] = new Use_([new UseUse(new Name(Api::class))]);
            $uses[] = new Use_([new UseUse(new Name(ApiOperation::class))]);
            $uses[] = new Use_([new UseUse(new Name(PostMapping::class))]);
            $uses[] = new Use_([new UseUse(new Name(ApiResponse::class))]);
            $uses[] = new Use_([new UseUse(new Name(AbstractController::class))]);
            $uses[] = new Use_([new UseUse(new Name(RequestBody::class))]);
            $uses[] = new Use_([new UseUse(new Name(Valid::class))]);
            array_push($namespace->stmts, ...$uses);
            $node->extends = new Name("AbstractController");
            $stmts = $this->buildControllerProperty();
            $methods = $this->buildControllerMethods();
            array_push($stmts, ...$methods);
            array_push($node->stmts, ...$stmts);
            $arg = new Arg(new String_("api/" . Str::camel($this->className)));
            $arg->name = new Identifier("prefix");
            $attribute = new Attribute(new Name("Controller"), [$arg]);
            $attributeGroup[] = new AttributeGroup([$attribute]);
            $arg = new Arg(new String_($this->classComment . "管理"));
            $arg->name = new Identifier("tags");
            $arg2 = new Arg(new LNumber(1));
            $arg2->name = new Identifier("position");
            $attribute = new Attribute(new Name("Api"), [$arg, $arg2]);
            $attributeGroup[] = new AttributeGroup([$attribute]);
            $node->attrGroups = $attributeGroup;
            return (new Standard())->prettyPrintFile([$declareStrictTypes, $namespace, $node]);
        }
    }

    private function buildControllerProperty(): array
    {
        $pros = [];
        $pro = new Property(
            Class_::MODIFIER_PROTECTED, // 访问修饰符
            [new PropertyProperty(Str::camel($this->className) . "Service")], // 属性名
            [],
            $this->className . "Service"
        );
        $attribute = new Attribute(new Name("Inject"));
        $attributeGroup[] = new AttributeGroup([$attribute]);
        $pro->attrGroups = $attributeGroup;
        $pros[] = $pro;
        return $pros;
    }

    private function buildServiceProperty(): array
    {
        $pros = [];
        $pro = new Property(
            Class_::MODIFIER_PROTECTED, // 访问修饰符
            [new PropertyProperty(Str::camel($this->className) . "Mapper")], // 属性名
            [],
            $this->className . "Mapper"
        );
        $attribute = new Attribute(new Name("Inject"));
        $attributeGroup[] = new AttributeGroup([$attribute]);
        $pro->attrGroups = $attributeGroup;
        $pros[] = $pro;
        $pro = new Property(
            Class_::MODIFIER_PROTECTED, // 访问修饰符
            [new PropertyProperty("redis")], // 属性名
            [],
            "Redis"
        );
        $pro->attrGroups = $attributeGroup;
        $pros[] = $pro;
        return $pros;
    }

    private function buildProperty(): Property
    {
        $pro = new Property(
            Class_::MODIFIER_PROTECTED, // 访问修饰符
            [new PropertyProperty(Str::camel($this->className) . "Model")], // 属性名
            [],
            $this->className
        );
        $attribute = new Attribute(new Name("Inject"));
        $attributeGroup[] = new AttributeGroup([$attribute]);
        $pro->attrGroups = $attributeGroup;
        return $pro;
    }

    private function buildMethods(): array
    {
        $nodes[] = $this->buildMethodById();
        $nodes[] = $this->buildMethodCreate();
        $nodes[] = $this->buildMethodUpdate();
        $nodes[] = $this->buildMethodPageInfo();
        return $nodes;
    }

    private function buildControllerMethods(): array
    {
        $nodes[] = $this->buildControllerMethodById();
        $nodes[] = $this->buildControllerMethodPageInfo();
        $nodes[] = $this->buildServiceMethodCreate();
        $nodes[] = $this->buildServiceMethodUpdate();
        return $nodes;
    }

    private function buildServiceMethods(): array
    {
        $nodes[] = $this->buildServiceMethodById();
        $nodes[] = $this->buildServiceMethodCreateOrUpdate('create');
        $nodes[] = $this->buildServiceMethodCreateOrUpdate('update');
        $nodes[] = $this->buildServiceMethodPageInfo();
        return $nodes;
    }

    /**
     * @return ClassMethod
     */
    public function buildServiceMethodById(): ClassMethod
    {
        $byId = Str::camel($this->className . "Id");
        $node = new ClassMethod(Str::camel("get" . $this->className . "ById"), [
            'flags' => Class_::MODIFIER_PUBLIC,
            'params' => [new Param(new Variable($byId), null, "int")],
            'returnType' => $this->className,
        ]);
        $camelClassName = Str::camel($this->className);
        $node->stmts[] = new Expression(
            new Assign(
                new Variable($camelClassName),
                new MethodCall(
                    new PropertyFetch(
                        new Variable('this'),
                        new Identifier(Str::camel($this->className) . "Mapper"),
                    ),
                    new Identifier('getUserById'), // 调用的方法名
                    [
                        new Arg(new Variable($byId))
                    ]
                )
            )
        );
        $if = new If_(new BooleanNot(new Variable($camelClassName)));
        $if->stmts[] = new Expression(new Throw_(new New_(new Name('AppBadRequestException'), args: [
            new Arg(new String_($this->className . "不存在"))
        ]
        )));
        $node->stmts[] = $if;
        $node->stmts[] = new Return_(
            new Variable($camelClassName)
        );
        return $node;
    }

    public function buildServiceMethodPageInfo(): ClassMethod
    {
        $camelClassName = Str::camel($this->className);
        $methodClassName = "get" . $this->className . "PageInfo";
        $in = $camelClassName . "PageDtoIn";
        $node = new ClassMethod($methodClassName, [
            'flags' => Class_::MODIFIER_PUBLIC,
            'params' => [new Param(new Variable($in), null, $this->className . "PageDtoIn")],
            'returnType' => "LengthAwarePaginatorInterface",
        ]);
        $node->stmts[] = new Return_(
            new MethodCall(
                new PropertyFetch(
                    new Variable('this'),
                    new Identifier(Str::camel($this->className) . "Mapper"),
                ), // 调用的对象
                new Identifier($methodClassName), // 调用的方法名
                [
                    new Arg(
                        new Variable($in)
                    )
                ]
            )
            ,
        );
        return $node;
    }

    private function buildServiceMethodCreateOrUpdate($type): ClassMethod
    {
        $camelClassName = Str::camel($this->className);
        $ucFirstType = Str::ucfirst($type);
        $createClassName = $type . $this->className;
        $in = $camelClassName . $ucFirstType . "DtoIn";
        $param = new Param(new Variable($in), null, $this->className . $ucFirstType . "DtoIn");
        $node = new ClassMethod($createClassName, [
            'flags' => Class_::MODIFIER_PUBLIC,
            'params' => [$param],
            'returnType' => $this->className,
        ]);
        $attribute = new Attribute(new Name("Transactional"));
        $attributeGroup[] = new AttributeGroup([$attribute]);
        $node->attrGroups = $attributeGroup;

        $node->stmts[] = new Expression(
            new Assign(
                new Variable('redisKey'),
                new MethodCall(
                    new ClassConstFetch(
                        new Name($this->className . "Enum"), // 调用方法的对象,
                        new VarLikeIdentifier(Str::upper($type) . "_LOCK_KEY"),
                    ),
                    new Identifier("getCode"), // 被调用的方法名
                ),

            )
        );
        $class = $this->data->getClass();
        $inId = "get" . Str::ucfirst(Str::camel((new $class)->getKeyName()));
        if ($type === 'update') {
            $stmts[] = new Expression(
                new Assign(
                    new Variable($camelClassName),
                    new MethodCall(
                        new Variable('this'),
                        new Identifier("get" . $this->className . "ById"), // 被调用的方法名
                        [
                            new Arg(
                                new MethodCall(
                                    new Variable($in),
                                    new Identifier($inId)
                                )
                            )
                        ]
                    ),
                )
            );
            $stmts[] = new Return_(
                new MethodCall(
                    new PropertyFetch(
                        new Variable('this'),
                        new Identifier($camelClassName . "Mapper"),
                    ),
                    new Identifier($createClassName),
                    [new Arg(new Variable($in)),

                        new Arg(new Variable($camelClassName))]
                )
            );
        } else {
            $stmts[] = new Return_(
                new MethodCall(
                    new PropertyFetch(
                        new Variable('this'),
                        new Identifier($camelClassName . "Mapper"),
                    ),
                    new Identifier($createClassName),
                    [new Arg(new Variable($in)),]
                )
            );
        }

        $node->stmts[] =
            new Expression(
                new Assign(
                    new Variable($camelClassName),
                    new MethodCall(
                        new New_(new Name('RedisLock'), array(
                            new Arg(
                                new PropertyFetch(
                                    new Variable('this'),
                                    new Identifier("redis"),
                                ),
                            ),
                            new Arg(new Variable("redisKey")),
                            new Arg(new LNumber(3)),
                        )),
                        new Identifier("get"), // 被调用的方法名
                        [
                            new Arg(
                                new Closure(subNodes: [
                                    'uses' => [
                                        new Variable($in)
                                    ],
                                    'stmts' => $stmts
                                ])
                            )
                        ]
                    )
                ));
        $if = new If_(new BooleanNot(new Variable($camelClassName)));
        $if->stmts[] = new Expression(new Throw_(new New_(new Name('AppBadRequestException'), args: [
            new Arg(new String_("请求频繁稍后在试"))
        ]
        )));
        $node->stmts[] = $if;

        $node->stmts[] = new Return_(
            new Variable($camelClassName)
        );
        return $node;
    }

    public function buildControllerMethodById(): ClassMethod
    {
        $camelClassName = Str::camel($this->className);
        $in = $camelClassName . "ByIdDtoIn";
        $methodName = Str::camel("get" . $this->className . "ById");
        $node = $this->getNode($in, $methodName);
        $class = $this->data->getClass();
        $inId = "get" . Str::ucfirst(Str::camel((new $class)->getKeyName()));
        $arg = new Arg(new String_($this->classComment . "详情"));
        $arg->name = new Identifier("summary");
        $attribute1 = new Attribute(new Name("ApiOperation"), [$arg]);
        $arg = new Arg(new String_("v1.0/" . $methodName));
        $arg->name = new Identifier("path");
        $attribute2 = new Attribute(new Name("PostMapping"), [$arg]);
        $arg = new Arg(new New_(new Name("ResponseClass"), args: [
            new Arg(new New_(new Name(Str::ucfirst($camelClassName) . "InfoDtoOut"), args: []))
        ]));
        $arg->name = new Identifier("returnType");
        $attribute3 = new Attribute(new Name("ApiResponse"), [$arg]);
        $attributeGroup[] = new AttributeGroup([$attribute1]);
        $attributeGroup[] = new AttributeGroup([$attribute2]);
        $attributeGroup[] = new AttributeGroup([$attribute3]);
        $node->attrGroups = $attributeGroup;
        $node->stmts[] = new Expression(new Assign(
            new Variable("info")
            , new MethodCall(
            new PropertyFetch(
                new Variable('this'),
                new Identifier(Str::camel($this->className) . "Service"),
            ), // 调用的对象
            new Identifier($methodName), // 调用的方法名
            [
                new Arg(
                    new MethodCall(
                        new Variable($in),
                        new Identifier($inId)
                    )
                )
            ]
        )));
        $node->stmts[] = new Return_(
            new MethodCall(
                new PropertyFetch(
                    new Variable('this'),
                    new Identifier("response"),
                ),
                new Identifier("success"),
                [
                    new Arg(
                        new Variable("info"),
                    ), new Arg(
                    new ClassConstFetch(
                        new Name(Str::ucfirst($camelClassName) . "InfoDtoOut"), // 调用方法的对象,
                        new VarLikeIdentifier("class"),
                    ),
                )
                ]
            )
        );
        return $node;
    }

    public function buildControllerMethodPageInfo(): ClassMethod
    {
        $camelClassName = Str::camel($this->className);
        $in = $camelClassName . "PageDtoIn";
        $methodName = Str::camel("get" . $this->className . "PageInfo");
        $node = $this->getNode($in, $methodName);
        $arg = new Arg(new String_($camelClassName . "分页列表"));
        $arg->name = new Identifier("summary");
        $attribute1 = new Attribute(new Name("ApiOperation"), [$arg]);
        $arg = new Arg(new String_("v1.0/" . $methodName));
        $arg->name = new Identifier("path");
        $attribute2 = new Attribute(new Name("PostMapping"), [$arg]);
        $arg = new Arg(new New_(new Name("ResponseClass"), args: [
            new Arg(new New_(new Name(Str::ucfirst($camelClassName) . "ListDtoOut"), args: []))
        ]));
        $arg->name = new Identifier("returnType");
        $attribute3 = new Attribute(new Name("ApiResponse"), [$arg]);
        $attributeGroup[] = new AttributeGroup([$attribute1]);
        $attributeGroup[] = new AttributeGroup([$attribute2]);
        $attributeGroup[] = new AttributeGroup([$attribute3]);
        $node->attrGroups = $attributeGroup;
        $node->stmts[] = new Expression(new Assign(
            new Variable("pageInfo")
            , new MethodCall(
            new PropertyFetch(
                new Variable('this'),
                new Identifier(Str::camel($this->className) . "Service"),
            ), // 调用的对象
            new Identifier($methodName), // 调用的方法名
            [
                new Arg(
                    new Variable($in)
                )
            ]
        )));
        $node->stmts[] = new Return_(
            new MethodCall(
                new PropertyFetch(
                    new Variable('this'),
                    new Identifier("response"),
                ),
                new Identifier("success"),
                [
                    new Arg(
                        new Variable("pageInfo"),
                    ), new Arg(
                    new ClassConstFetch(
                        new Name(Str::ucfirst($camelClassName) . "ListDtoOut"), // 调用方法的对象,
                        new VarLikeIdentifier("class"),
                    ),
                )
                ]
            )
        );
        return $node;
    }

    public function buildServiceMethodCreate(): ClassMethod
    {
        $camelClassName = Str::camel($this->className);
        $in = $camelClassName . "CreateDtoIn";
        $methodName = Str::camel("create" . $this->className);
        $node = $this->getNode($in, $methodName);
        $arg = new Arg(new String_($this->classComment . "创建"));
        return $this->extracted($arg, $methodName, $node, $in);
    }

    public function buildServiceMethodUpdate(): ClassMethod
    {
        $camelClassName = Str::camel($this->className);
        $in = $camelClassName . "UpdateDtoIn";
        $methodName = Str::camel("update" . $this->className);
        $node = $this->getNode($in, $methodName);
        $arg = new Arg(new String_($this->classComment . "编辑"));
        return $this->extracted($arg, $methodName, $node, $in);
    }

    public function buildMethodById(): ClassMethod
    {
        $byId = Str::camel($this->className . "Id");
        $node = new ClassMethod(Str::camel("get" . $this->className . "ById"), [
            'flags' => Class_::MODIFIER_PUBLIC,
            'params' => [new Param(new Variable($byId), null, "int")],
            'returnType' => $this->className . "|null",
        ]);
        $node->stmts[] = new Return_(
            new MethodCall(
                new MethodCall(
                    new PropertyFetch(
                        new Variable('this'),
                        new Identifier(Str::camel($this->className) . "Model"),
                    ), // 调用的对象
                    new Identifier('newModelQuery'), // 调用的方法名
                ),
                new Identifier('find'), // 调用的方法名
                [
                    new Arg(new Variable($byId))
                ]
            )
            ,
        );
        return $node;
    }

    private function buildMethodCreate(): ClassMethod
    {
        $camelClassName = Str::camel($this->className);
        $createClassName = "create" . $this->className;
        $in = $camelClassName . "CreateDtoIn";
        $node = new ClassMethod($createClassName, [
            'flags' => Class_::MODIFIER_PUBLIC,
            'params' => [new Param(new Variable($in), null, $this->className . "CreateDtoIn")],
            'returnType' => $this->className,
        ]);

        $node->stmts[] = new Expression(
            new Assign(
                new Variable($camelClassName),
                new New_(
                    new Name($this->className), // 类名
                )
            )
        );
        $node->stmts[] = new Expression(
            new Assign(
                new Variable($camelClassName),
                new StaticCall(
                    new Name("Mapper"), // 调用方法的对象
                    new Identifier("copyProperties"), // 被调用的方法名
                    [
                        new Arg(new Variable($in)),
                        new Arg(new Variable($camelClassName)),
                    ]
                ),
            )
        );
        $node->stmts[] =
            new Expression(
                new MethodCall(
                    new Variable($camelClassName),
                    new Identifier("save")
                )
            );

        $node->stmts[] = new Return_(
            new Variable($camelClassName)
        );
        return $node;
    }

    private function buildMethodPageInfo(): ClassMethod
    {
        $camelClassName = Str::camel($this->className);
        $methodClassName = "get" . $this->className . "PageInfo";
        $in = $camelClassName . "PageDtoIn";
        $node = new ClassMethod($methodClassName, [
            'flags' => Class_::MODIFIER_PUBLIC,
            'params' => [new Param(new Variable($in), null, $this->className . "PageDtoIn")],
            'returnType' => "LengthAwarePaginatorInterface",
        ]);

        $listSelect = new PropertyFetch(
            new Variable('this'),
            new Identifier(Str::camel($this->className) . "Model")
        );


        $node->stmts[] = new Return_(
            new MethodCall(
                new MethodCall(
                    new PropertyFetch(
                        new Variable('this'),
                        new Identifier(Str::camel($this->className) . "Model"),
                    ), // 调用的对象
                    new Identifier('newModelQuery'), // 调用的方法名
                ),
                new Identifier('paginate'), // 调用的方法名
                [
                    new Arg(
                        new MethodCall(
                            new Variable($in),
                            new Identifier("getPageSize")
                        )),
                    new Arg(
                        new PropertyFetch(
                            $listSelect,
                            new Identifier("listSelect")
                        )
                    ),
                    new Arg(
                        new String_("pageNo"))
                ]
            )
            ,
        );
        return $node;
    }

    private function buildMethodUpdate(): ClassMethod
    {
        $camelClassName = Str::camel($this->className);
        $createClassName = "update" . $this->className;
        $in = $camelClassName . "UpdateDtoIn";
        $node = new ClassMethod($createClassName, [
            'flags' => Class_::MODIFIER_PUBLIC,
            'params' => [new Param(new Variable($in), null, $this->className . "UpdateDtoIn"),
                new Param(new Variable($camelClassName . "One"), null, $this->className)],
            'returnType' => $this->className,
        ]);

        $node->stmts[] = new Expression(
            new Assign(
                new Variable($camelClassName),
                new StaticCall(
                    new Name("Mapper"), // 调用方法的对象
                    new Identifier("copyProperties"), // 被调用的方法名
                    [
                        new Arg(new Variable($in)),
                        new Arg(new Variable($camelClassName . "One")),
                    ]
                ),
            )
        );
        $node->stmts[] =
            new Expression(
                new MethodCall(
                    new Variable($camelClassName),
                    new Identifier("save")
                )
            );

        $node->stmts[] = new Return_(
            new Variable($camelClassName)
        );
        return $node;
    }

    /**
     * @param Arg $arg
     * @param string $methodName
     * @param ClassMethod $node
     * @param string $in
     * @return ClassMethod
     */
    public function extracted(Arg $arg, string $methodName, ClassMethod $node, string $in): ClassMethod
    {
        $arg->name = new Identifier("summary");
        $attribute1 = new Attribute(new Name("ApiOperation"), [$arg]);
        $arg = new Arg(new String_("v1.0/" . $methodName));
        $arg->name = new Identifier("path");
        $attribute2 = new Attribute(new Name("PostMapping"), [$arg]);
        $attributeGroup[] = new AttributeGroup([$attribute1]);
        $attributeGroup[] = new AttributeGroup([$attribute2]);
        $node->attrGroups = $attributeGroup;
        $node->stmts[] = new Expression(
            new MethodCall(
                new PropertyFetch(
                    new Variable('this'),
                    new Identifier(Str::camel($this->className) . "Service"),
                ), // 调用的对象
                new Identifier($methodName), // 调用的方法名
                [
                    new Arg(
                        new Variable($in)
                    )
                ]
            ));
        $node->stmts[] = new Return_(
            new MethodCall(
                new PropertyFetch(
                    new Variable('this'),
                    new Identifier("response"),
                ),
                new Identifier("success"),
                [
                    new Arg(
                        new String_("ok")
                    )
                ]
            )
        );
        return $node;
    }

    /**
     * @param string $in
     * @param string $methodName
     * @return ClassMethod
     */
    public function getNode(string $in, string $methodName): ClassMethod
    {
        $attributes[] = new Attribute(new Name("RequestBody"));
        $attributes[] = new Attribute(new Name("Valid"));
        $paramAttributeGroup[] = new AttributeGroup($attributes);
        $param = new Param(new Variable($in), null, Str::ucfirst($in));
        $param->attrGroups = $paramAttributeGroup;
        $node = new ClassMethod(Str::camel($methodName), [
            'flags' => Class_::MODIFIER_PUBLIC,
            'params' => [$param],
            'returnType' => "ResponseClass",
        ]);
        return $node;
    }
}