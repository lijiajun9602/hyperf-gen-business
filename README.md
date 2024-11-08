# PHP Gen Business Api 
基于 [Hyperf](https://github.com/hyperf/hyperf) 框架的 根据表生成相应的接口

## 优点

- 可生成相应的接口代码
- 可生成相应的接口文档
- 可生成枚举类和出参入参类可方便使用
- 增加了分布式锁
- 支持PHP8原生注解，PHP8.2枚举


## 使用须知

* php版本 >= 8.2，参数映射到PHP类不支持联合类型


## 安装

```
composer require lijiajun9602/hyperf-gen-business

```

## 使用


### 1. 发布配置文件

```bash
php bin/hyperf.php vendor:publish lijiajun9602/hyperf-gen-business
```

#### 1.1 配置信息

> config/autoload/api_docs.php
> config/autoload/gen-business.php
> config/autoload/php-accessor.php

#### 1.2 使用 
> config/autoload/databases.php
```php
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

use Hyperf\Database\Commands\Ast\ModelRewriteKeyInfoVisitor;
use Hyperf\GenBusiness\Visitor\{BusinessVisitor, GenDtoVisitor, ModelRewriteGetterSetterVisitor};
use function Hyperf\Support\env;

return [
    'default' => [
        'driver' => env('DB_DRIVER', 'mysql'),
        'host' => env('DB_HOST', 'localhost'),
        'database' => env('DB_DATABASE', 'hyperf'),
        'port' => env('DB_PORT', 3306),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => env('DB_CHARSET', 'utf8'),
        'collation' => env('DB_COLLATION', 'utf8_unicode_ci'),
        'prefix' => env('DB_PREFIX', ''),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
        ],
        'commands' => [
            'gen:model' => [
                'uses' => '',
                'table_mapping' => [],
                'with_comments' => true,
                'property_case' => Hyperf\Database\Commands\ModelOption::PROPERTY_CAMEL_CASE,
                'visitors' => [
                    ModelRewriteKeyInfoVisitor::class,
                    ModelRewriteGetterSetterVisitor::class,
                    GenDtoVisitor::class,
                    BusinessVisitor::class
                ],
            ],
        ],
    ],
];
```

```bash
php bin/hyperf.php gen:model
```

#### 1.2 使用
> 生成案例
> UserController
```php
<?php

declare (strict_types=1);
namespace App\Controller\gen;

use App\Service\gen\UserService;
use App\Model\User;
use App\Controller\Dto\User\In\UserByIdDtoIn;
use App\Controller\Dto\User\In\UserCreateDtoIn;
use App\Controller\Dto\User\In\UserPageDtoIn;
use App\Controller\Dto\User\In\UserUpdateDtoIn;
use App\Controller\Dto\User\Out\UserInfoDtoOut;
use App\Controller\Dto\User\Out\UserListDtoOut;
use Hyperf\Di\Annotation\Inject;
use Hyperf\GenBusiness\Common\Dto\ResponseClass;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\ApiDocs\Annotation\Api;
use Hyperf\ApiDocs\Annotation\ApiOperation;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\ApiDocs\Annotation\ApiResponse;
use Hyperf\GenBusiness\Common\Controller\AbstractController;
use Hyperf\DTO\Annotation\Contracts\RequestBody;
use Hyperf\DTO\Annotation\Contracts\Valid;
#[Controller(prefix: 'api/user')]
#[Api(tags: '用户表管理', position: 1)]
class UserController extends AbstractController
{
    #[Inject]
    protected UserService $userService;
    #[ApiOperation(summary: '用户表详情')]
    #[PostMapping(path: 'v1.0/getUserById')]
    #[ApiResponse(returnType: new ResponseClass(new UserInfoDtoOut()))]
    public function getUserById(#[RequestBody, Valid] UserByIdDtoIn $userByIdDtoIn) : ResponseClass
    {
        $info = $this->userService->getUserById($userByIdDtoIn->getUserId());
        return $this->response->success($info, UserInfoDtoOut::class);
    }
    #[ApiOperation(summary: 'user分页列表')]
    #[PostMapping(path: 'v1.0/getUserPageInfo')]
    #[ApiResponse(returnType: new ResponseClass(new UserListDtoOut()))]
    public function getUserPageInfo(#[RequestBody, Valid] UserPageDtoIn $userPageDtoIn) : ResponseClass
    {
        $pageInfo = $this->userService->getUserPageInfo($userPageDtoIn);
        return $this->response->success($pageInfo, UserListDtoOut::class);
    }
    #[ApiOperation(summary: '用户表创建')]
    #[PostMapping(path: 'v1.0/createUser')]
    public function createUser(#[RequestBody, Valid] UserCreateDtoIn $userCreateDtoIn) : ResponseClass
    {
        $this->userService->createUser($userCreateDtoIn);
        return $this->response->success('ok');
    }
    #[ApiOperation(summary: '用户表编辑')]
    #[PostMapping(path: 'v1.0/updateUser')]
    public function updateUser(#[RequestBody, Valid] UserUpdateDtoIn $userUpdateDtoIn) : ResponseClass
    {
        $this->userService->updateUser($userUpdateDtoIn);
        return $this->response->success('ok');
    }
}
```
> UserService

```php
<?php
<?php

declare (strict_types=1);
namespace App\Service\gen;

use App\Enums\gen\UserEnum;
use App\Mapper\gen\UserMapper;
use App\Model\User;
use App\Controller\Dto\User\In\UserCreateDtoIn;
use App\Controller\Dto\User\In\UserPageDtoIn;
use App\Controller\Dto\User\In\UserUpdateDtoIn;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Contract\LengthAwarePaginatorInterface;
use Hyperf\GenBusiness\Common\Exception\AppBadRequestException;
use Hyperf\Redis\Redis;
use Hyperf\GenBusiness\Common\Lock\RedisLock;
use Hyperf\DbConnection\Annotation\Transactional;
class UserService
{
    #[Inject]
    protected UserMapper $userMapper;
    #[Inject]
    protected Redis $redis;
    public function getUserById(int $userId) : User
    {
        $user = $this->userMapper->getUserById($userId);
        if (!$user) {
            throw new AppBadRequestException('User不存在');
        }
        return $user;
    }
    #[Transactional]
    public function createUser(UserCreateDtoIn $userCreateDtoIn) : User
    {
        $redisKey = UserEnum::CREATE_LOCK_KEY->getCode();
        $user = (new RedisLock($this->redis, $redisKey, 3))->get(function () use($userCreateDtoIn) {
            return $this->userMapper->createUser($userCreateDtoIn);
        });
        if (!$user) {
            throw new AppBadRequestException('请求频繁稍后在试');
        }
        return $user;
    }
    #[Transactional]
    public function updateUser(UserUpdateDtoIn $userUpdateDtoIn) : User
    {
        $redisKey = UserEnum::UPDATE_LOCK_KEY->getCode();
        $user = (new RedisLock($this->redis, $redisKey, 3))->get(function () use($userUpdateDtoIn) {
            $user = $this->getUserById($userUpdateDtoIn->getUserId());
            return $this->userMapper->updateUser($userUpdateDtoIn, $user);
        });
        if (!$user) {
            throw new AppBadRequestException('请求频繁稍后在试');
        }
        return $user;
    }
    public function getUserPageInfo(UserPageDtoIn $userPageDtoIn) : LengthAwarePaginatorInterface
    {
        return $this->userMapper->getUserPageInfo($userPageDtoIn);
    }
}
?>

```
> UserMapper
```php
<?php

declare (strict_types=1);
namespace App\Mapper\gen;

use App\Model\User;
use App\Controller\Dto\User\In\UserCreateDtoIn;
use App\Controller\Dto\User\In\UserPageDtoIn;
use App\Controller\Dto\User\In\UserUpdateDtoIn;
use Hyperf\Di\Annotation\Inject;
use Hyperf\DTO\Mapper;
use Hyperf\Contract\LengthAwarePaginatorInterface;
class UserMapper
{
    #[Inject]
    protected User $userModel;
    public function getUserById(int $userId) : User|null
    {
        return $this->userModel->newModelQuery()->find($userId);
    }
    public function createUser(UserCreateDtoIn $userCreateDtoIn) : User
    {
        $user = new User();
        $user = Mapper::copyProperties($userCreateDtoIn, $user);
        $user->save();
        return $user;
    }
    public function updateUser(UserUpdateDtoIn $userUpdateDtoIn, User $userOne) : User
    {
        $user = Mapper::copyProperties($userUpdateDtoIn, $userOne);
        $user->save();
        return $user;
    }
    public function getUserPageInfo(UserPageDtoIn $userPageDtoIn) : LengthAwarePaginatorInterface
    {
        return $this->userModel->newModelQuery()->paginate($userPageDtoIn->getPageSize(), $this->userModel->listSelect, 'pageNo');
    }
}
```
> UserEnum
```php
<?php

namespace App\Enums\gen;

use Lishun\Enums\Interfaces\EnumCodeInterface;
use Hyperf\GenBusiness\Common\Enums\NewEnumCodeGet;
use Lishun\Enums\Annotations\EnumCode;
enum UserEnum : string implements EnumCodeInterface
{
    use NewEnumCodeGet;
    #[EnumCode(msg: '创建锁')]
    case CREATE_LOCK_KEY = 'user:create_lock:';
    #[EnumCode(msg: '编辑锁')]
    case UPDATE_LOCK_KEY = 'user:update_lock:';
    #[EnumCode(msg: '删除锁')]
    case DELETE_LOCK_KEY = 'user:delete_lock:';
    #[EnumCode(msg: 'married')]
    case MARITAL_STATUS_MARRIED = '已婚';
    #[EnumCode(msg: 'unmarried')]
    case MARITAL_STATUS_UNMARRIED = '未婚';
    #[EnumCode(msg: '婚姻状态', ext: ['married' => '已婚', 'unmarried' => '未婚'])]
    case MARITAL_STATUS = 'marital_status';
    #[EnumCode(msg: '0')]
    case SEX_0 = '未知';
    #[EnumCode(msg: '1')]
    case SEX_1 = '男';
    #[EnumCode(msg: '2')]
    case SEX_2 = '女';
    #[EnumCode(msg: '性别', ext: ['0' => '未知', '1' => '男', '2' => '女'])]
    case SEX = 'sex';
    #[EnumCode(msg: '0')]
    case IS_DISABLED_0 = '未禁用';
    #[EnumCode(msg: '1')]
    case IS_DISABLED_1 = '已禁用';
    #[EnumCode(msg: '是否禁用', ext: ['0' => '未禁用', '1' => '已禁用'])]
    case IS_DISABLED = 'is_disabled';
}
```
> 入参
> UserByIdDtoIn
```php
<?php

namespace App\Controller\Dto\User\In;

use Hyperf\DTO\Annotation\Dto;
use PhpAccessor\Attribute\Data;
use Hyperf\PhpAccessor\Annotation\HyperfData;
use Hyperf\ApiDocs\Annotation\ApiModel;
use Hyperf\DTO\Annotation\Validation\Required;
use Hyperf\ApiDocs\Annotation\ApiModelProperty;
use Hyperf\GenBusiness\Common\Dto\UserJwtAuthIn;
#[Dto]
#[Data]
#[HyperfData]
#[ApiModel(value: 'UserByIdDtoIn入参')]
class UserByIdDtoIn extends UserJwtAuthIn
{
    #[ApiModelProperty(value: '用户ID Token获得')]
    public int $userId;
}
```
> UserCreateDtoIn
```php
<?php

namespace App\Controller\Dto\User\In;

use Hyperf\DTO\Annotation\Dto;
use PhpAccessor\Attribute\Data;
use Hyperf\PhpAccessor\Annotation\HyperfData;
use Hyperf\ApiDocs\Annotation\ApiModel;
use Hyperf\DTO\Annotation\Validation\Required;
use Hyperf\ApiDocs\Annotation\ApiModelProperty;
use Hyperf\DTO\Annotation\Validation\In;
use Hyperf\GenBusiness\Common\Dto\UserJwtAuthIn;
#[Dto]
#[Data]
#[HyperfData]
#[ApiModel(value: 'UserCreateDtoIn入参')]
class UserCreateDtoIn extends UserJwtAuthIn
{
    #[ApiModelProperty(value: '名称')]
    #[Required(messages: '名称不能为空')]
    public ?string $nickName;
    #[ApiModelProperty(value: '手机号')]
    #[Required(messages: '手机号不能为空')]
    public ?string $mobile;
    #[ApiModelProperty(value: '婚姻状态:married-已婚,unmarried-未婚')]
    #[Required(messages: '婚姻状态不能为空')]
    #[In(value: ['married', 'unmarried'], messages: '婚姻状态格式错误')]
    public ?string $maritalStatus;
    #[ApiModelProperty(value: '密码')]
    #[Required(messages: '密码不能为空')]
    public ?string $password;
    #[ApiModelProperty(value: '性别:0-未知,1-男,2-女')]
    #[Required(messages: '性别不能为空')]
    #[In(value: ['0', '1', '2'], messages: '性别格式错误')]
    public ?string $sex;
    #[ApiModelProperty(value: '是否禁用:0-未禁用,1-已禁用')]
    #[Required(messages: '是否禁用不能为空')]
    #[In(value: ['0', '1'], messages: '是否禁用格式错误')]
    public ?string $isDisabled;
    #[ApiModelProperty(value: '登陆IP')]
    #[Required(messages: '登陆IP不能为空')]
    public ?string $ip;
    #[ApiModelProperty(value: '最后登陆时间')]
    #[Required(messages: '最后登陆时间不能为空')]
    public ?string $loginAt;
}

```
> UserPageDtoIn
```php
<?php

namespace App\Controller\Dto\User\In;

use Hyperf\DTO\Annotation\Dto;
use PhpAccessor\Attribute\Data;
use Hyperf\PhpAccessor\Annotation\HyperfData;
use Hyperf\ApiDocs\Annotation\ApiModel;
use Hyperf\GenBusiness\Common\Dto\PageClass;
use Hyperf\GenBusiness\Common\Dto\UserJwtAuthIn;
#[Dto]
#[Data]
#[HyperfData]
#[ApiModel(value: 'UserPageDtoIn入参')]
class UserPageDtoIn extends UserJwtAuthIn
{
    use PageClass;
}

```
> UserUpdateDtoIn
```php
<?php

namespace App\Controller\Dto\User\In;

use Hyperf\DTO\Annotation\Dto;
use PhpAccessor\Attribute\Data;
use Hyperf\PhpAccessor\Annotation\HyperfData;
use Hyperf\ApiDocs\Annotation\ApiModel;
use Hyperf\DTO\Annotation\Validation\Required;
use Hyperf\ApiDocs\Annotation\ApiModelProperty;
use Hyperf\DTO\Annotation\Validation\In;
use Hyperf\GenBusiness\Common\Dto\UserJwtAuthIn;
#[Dto]
#[Data]
#[HyperfData]
#[ApiModel(value: 'UserUpdateDtoIn入参')]
class UserUpdateDtoIn extends UserJwtAuthIn
{
    #[ApiModelProperty(value: '名称')]
    #[Required(messages: '名称不能为空')]
    public ?string $nickName;
    #[ApiModelProperty(value: '手机号')]
    #[Required(messages: '手机号不能为空')]
    public ?string $mobile;
    #[ApiModelProperty(value: '婚姻状态:married-已婚,unmarried-未婚')]
    #[Required(messages: '婚姻状态不能为空')]
    #[In(value: ['married', 'unmarried'], messages: '婚姻状态格式错误')]
    public ?string $maritalStatus;
    #[ApiModelProperty(value: '密码')]
    #[Required(messages: '密码不能为空')]
    public ?string $password;
    #[ApiModelProperty(value: '性别:0-未知,1-男,2-女')]
    #[Required(messages: '性别不能为空')]
    #[In(value: ['0', '1', '2'], messages: '性别格式错误')]
    public ?string $sex;
    #[ApiModelProperty(value: '是否禁用:0-未禁用,1-已禁用')]
    #[Required(messages: '是否禁用不能为空')]
    #[In(value: ['0', '1'], messages: '是否禁用格式错误')]
    public ?string $isDisabled;
    #[ApiModelProperty(value: '登陆IP')]
    #[Required(messages: '登陆IP不能为空')]
    public ?string $ip;
    #[ApiModelProperty(value: '最后登陆时间')]
    #[Required(messages: '最后登陆时间不能为空')]
    public ?string $loginAt;
}

```
> 出参数
> UserInfoDtoOut
```php
<?php

namespace App\Controller\Dto\User\Out;

use Hyperf\DTO\Annotation\Dto;
use Hyperf\ApiDocs\Annotation\ApiModelProperty;
use App\Enums\gen\UserEnum;
#[Dto]
class UserInfoDtoOut
{
    #[ApiModelProperty(value: '用户ID')]
    public ?int $userId;
    #[ApiModelProperty(value: '名称')]
    public ?string $nickName;
    #[ApiModelProperty(value: '手机号')]
    public ?string $mobile;
    #[ApiModelProperty(value: '婚姻状态:married-已婚,unmarried-未婚中文标识')]
    public ?string $maritalStatusName;
    #[ApiModelProperty(value: '婚姻状态:married-已婚,unmarried-未婚')]
    public ?string $maritalStatus;
    #[ApiModelProperty(value: '密码')]
    public ?string $password;
    #[ApiModelProperty(value: '性别:0-未知,1-男,2-女中文标识')]
    public ?string $sexName;
    #[ApiModelProperty(value: '性别:0-未知,1-男,2-女')]
    public ?string $sex;
    #[ApiModelProperty(value: '是否禁用:0-未禁用,1-已禁用中文标识')]
    public ?string $isDisabledName;
    #[ApiModelProperty(value: '是否禁用:0-未禁用,1-已禁用')]
    public ?string $isDisabled;
    #[ApiModelProperty(value: '登陆IP')]
    public ?string $ip;
    #[ApiModelProperty(value: '最后登陆时间')]
    public ?string $loginAt;
    #[ApiModelProperty(value: '行锁')]
    public ?int $lock;
    #[ApiModelProperty(value: '')]
    public ?string $createdAt;
    #[ApiModelProperty(value: '')]
    public ?string $updatedAt;
    #[ApiModelProperty(value: '')]
    public ?string $deletedAt;
    public function setMaritalStatus(string $maritalStatus) : static
    {
        $this->maritalStatus = $maritalStatus;
        $this->maritalStatusName = UserEnum::MARITAL_STATUS->getExt($maritalStatus);
        return $this;
    }
    public function setSex(string $sex) : static
    {
        $this->sex = $sex;
        $this->sexName = UserEnum::SEX->getExt($sex);
        return $this;
    }
    public function setIsDisabled(string $isDisabled) : static
    {
        $this->isDisabled = $isDisabled;
        $this->isDisabledName = UserEnum::IS_DISABLED->getExt($isDisabled);
        return $this;
    }
}
```
> UserListDtoOut
```php
<?php

namespace App\Controller\Dto\User\Out;

use Hyperf\DTO\Annotation\Dto;
use Hyperf\ApiDocs\Annotation\ApiModelProperty;
use App\Enums\gen\UserEnum;
#[Dto]
class UserListDtoOut
{
    #[ApiModelProperty(value: '用户ID')]
    public ?int $userId;
    #[ApiModelProperty(value: '名称')]
    public ?string $nickName;
    #[ApiModelProperty(value: '手机号')]
    public ?string $mobile;
    #[ApiModelProperty(value: '婚姻状态:married-已婚,unmarried-未婚中文标识')]
    public ?string $maritalStatusName;
    #[ApiModelProperty(value: '婚姻状态:married-已婚,unmarried-未婚')]
    public ?string $maritalStatus;
    #[ApiModelProperty(value: '密码')]
    public ?string $password;
    #[ApiModelProperty(value: '性别:0-未知,1-男,2-女中文标识')]
    public ?string $sexName;
    #[ApiModelProperty(value: '性别:0-未知,1-男,2-女')]
    public ?string $sex;
    #[ApiModelProperty(value: '是否禁用:0-未禁用,1-已禁用中文标识')]
    public ?string $isDisabledName;
    #[ApiModelProperty(value: '是否禁用:0-未禁用,1-已禁用')]
    public ?string $isDisabled;
    #[ApiModelProperty(value: '登陆IP')]
    public ?string $ip;
    #[ApiModelProperty(value: '最后登陆时间')]
    public ?string $loginAt;
    #[ApiModelProperty(value: '行锁')]
    public ?int $lock;
    #[ApiModelProperty(value: '')]
    public ?string $createdAt;
    #[ApiModelProperty(value: '')]
    public ?string $updatedAt;
    #[ApiModelProperty(value: '')]
    public ?string $deletedAt;
    public function setMaritalStatus(string $maritalStatus) : static
    {
        $this->maritalStatus = $maritalStatus;
        $this->maritalStatusName = UserEnum::MARITAL_STATUS->getExt($maritalStatus);
        return $this;
    }
    public function setSex(string $sex) : static
    {
        $this->sex = $sex;
        $this->sexName = UserEnum::SEX->getExt($sex);
        return $this;
    }
    public function setIsDisabled(string $isDisabled) : static
    {
        $this->isDisabled = $isDisabled;
        $this->isDisabledName = UserEnum::IS_DISABLED->getExt($isDisabled);
        return $this;
    }
}
```
#### 1.2 注意
> 数据库枚举类注释请按下格式编写
> 婚姻状态:married-已婚,unmarried-未婚







