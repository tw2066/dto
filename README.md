# Hyperf DTO

[![Latest Stable Version](https://img.shields.io/packagist/v/tangwei/dto)](https://packagist.org/packages/tangwei/dto)
[![Total Downloads](https://img.shields.io/packagist/dt/tangwei/dto)](https://packagist.org/packages/tangwei/dto)
[![License](https://img.shields.io/packagist/l/tangwei/dto)](https://github.com/tw2066/dto)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-blue)](https://www.php.net)

[English](./README_EN.md) | 中文

基于 [Hyperf](https://github.com/hyperf/hyperf) 框架的 DTO（数据传输对象）映射和验证库，使用 PHP 8 Attributes 特性，提供优雅的请求参数绑定和验证方案。

## ✨ 特性

- 🚀 **自动映射** — 请求参数自动映射到 PHP DTO 类
- 🎯 **类型安全** — 利用 PHP 8.2+ 的类型系统，提供完整的类型提示
- 🔄 **递归支持** — 支持数组、嵌套对象、递归结构（最大嵌套深度 100）
- ✅ **数据验证** — 集成 Hyperf 验证器，提供约 90 个验证注解
- 📝 **多种参数源** — 支持 Body、Query、FormData、Header 等多种参数来源
- 🎨 **代码优雅** — 基于 PHP 8 Attributes，代码简洁易读
- 🔧 **易于扩展** — 支持自定义验证规则和响应字段名转换
- 📡 **RPC 支持** — 兼容 JSON-RPC（TCP/HTTP）服务的参数验证

## 📋 环境要求

- PHP >= 8.2
- Hyperf ~3.2
- （可选）hyperf/validation — 数据验证
- （可选）symfony/serializer + symfony/property-access — RPC 对象序列化

## 📦 安装

```bash
composer require tangwei/dto
```

安装后组件通过 `ConfigProvider` 自动注册，无需额外配置。

## 📖 快速开始

### 基本使用

#### 1. 创建 DTO 类

```php
namespace App\Request;

use Hyperf\DTO\Annotation\Validation\Required;
use Hyperf\DTO\Annotation\Validation\Integer;
use Hyperf\DTO\Annotation\Validation\Between;

class DemoQuery
{
    public string $name;

    #[Required]
    #[Integer]
    #[Between(1, 100)]
    public int $age;
}
```

#### 2. 在控制器中使用

```php
namespace App\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\DTO\Annotation\Contracts\RequestQuery;
use Hyperf\DTO\Annotation\Contracts\Valid;
use App\Request\DemoQuery;

#[Controller(prefix: '/user')]
class UserController
{
    #[GetMapping(path: 'info')]
    public function info(#[RequestQuery] #[Valid] DemoQuery $request): array
    {
        return [
            'name' => $request->name,
            'age' => $request->age,
        ];
    }
}
```

请求 `/user/info?name=tom&age=20` 时，`$request` 会自动填充并验证；验证失败抛出 `Hyperf\Validation\ValidationException`。

## 📚 注解说明

### 参数来源注解

> 命名空间：`Hyperf\DTO\Annotation\Contracts`

#### RequestBody

获取 POST/PUT/PATCH 请求的 Body 参数：

```php
use Hyperf\DTO\Annotation\Contracts\RequestBody;

#[PostMapping(path: 'create')]
public function create(#[RequestBody] CreateUserRequest $request)
{
    // $request 会自动填充 Body 中的数据
}
```

#### RequestQuery

获取 URL 查询参数（GET 参数）：

```php
use Hyperf\DTO\Annotation\Contracts\RequestQuery;

#[GetMapping(path: 'list')]
public function list(#[RequestQuery] QueryRequest $request)
{
    // $request 会自动填充 Query 参数
}
```

#### RequestFormData

获取表单请求数据（Content-Type: multipart/form-data）：

```php
use Hyperf\DTO\Annotation\Contracts\RequestFormData;

#[PostMapping(path: 'upload')]
public function upload(#[RequestFormData] UploadRequest $formData)
{
    // $formData 会自动填充表单数据
    // 文件上传需要通过 $this->request->file('field_name') 获取
}
```

#### RequestHeader

获取请求头信息（一个方法中最多只能有一个 `RequestHeader` 参数）：

```php
use Hyperf\DTO\Annotation\Contracts\RequestHeader;

#[GetMapping(path: 'info')]
public function info(#[RequestHeader] HeaderRequest $headers)
{
    // $headers 会自动填充请求头数据
}
```

#### Valid

启用验证，必须与参数来源注解一起使用：

```php
#[PostMapping(path: 'create')]
public function create(#[RequestBody] #[Valid] CreateUserRequest $request)
{
    // 请求参数会先验证，验证失败会自动抛出异常
}
```

### 组合使用

可以在同一方法中组合使用多种参数来源：

```php
#[PutMapping(path: 'update/{id}')]
public function update(
    int $id,
    #[RequestBody] #[Valid] UpdateRequest $body,
    #[RequestQuery] QueryRequest $query,
    #[RequestHeader] HeaderRequest $headers
) {
    // 同时获取 Body、Query 和 Header 参数
}
```

> ⚠️ **注意**：
> - 同一参数上 `RequestBody`、`RequestQuery`、`RequestFormData` 互斥，只能标注其一
> - 同一方法中 `RequestBody` 与 `RequestFormData` 不能同时存在于不同参数上
> - 违反以上约束会在服务启动扫描阶段抛出 `Hyperf\DTO\Exception\DtoException`，提前暴露错误

## 📝 完整示例

### 控制器示例

```php
namespace App\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\DTO\Annotation\Contracts\RequestBody;
use Hyperf\DTO\Annotation\Contracts\RequestQuery;
use Hyperf\DTO\Annotation\Contracts\RequestFormData;
use Hyperf\DTO\Annotation\Contracts\Valid;

#[Controller(prefix: '/demo')]
class DemoController
{
    #[GetMapping(path: 'query')]
    public function query(#[RequestQuery] #[Valid] DemoQuery $request): array
    {
        return [
            'name' => $request->name,
            'age' => $request->age,
        ];
    }

    #[PostMapping(path: 'create')]
    public function create(#[RequestBody] #[Valid] CreateRequest $request): array
    {
        // 处理创建逻辑
        return ['id' => 1, 'message' => 'Created successfully'];
    }

    #[PutMapping(path: 'update')]
    public function update(
        #[RequestBody] #[Valid] UpdateRequest $body,
        #[RequestQuery] QueryParams $query
    ): array {
        // 同时使用 Body 和 Query 参数
        return ['message' => 'Updated successfully'];
    }

    #[PostMapping(path: 'upload')]
    public function upload(#[RequestFormData] UploadRequest $formData): array
    {
        $file = $this->request->file('photo');
        // 处理文件上传
        return ['message' => 'Uploaded successfully'];
    }
}
```

### DTO 类示例

#### 简单 DTO

```php
namespace App\Request;

use Hyperf\DTO\Annotation\Validation\Required;
use Hyperf\DTO\Annotation\Validation\Integer;
use Hyperf\DTO\Annotation\Validation\Between;
use Hyperf\DTO\Annotation\Validation\Email;

class CreateRequest
{
    #[Required]
    public string $name;

    #[Required]
    #[Email]
    public string $email;

    #[Required]
    #[Integer]
    #[Between(18, 100)]
    public int $age;
}
```

#### 嵌套对象 DTO

嵌套对象会递归映射并递归验证（验证规则取嵌套类自身的注解）：

```php
namespace App\Request;

class UserRequest
{
    public string $name;

    public int $age;

    // 嵌套对象
    public Address $address;
}

class Address
{
    public string $province;

    public string $city;

    public string $street;
}
```

#### 数组类型 DTO

```php
namespace App\Request;

use Hyperf\DTO\Annotation\ArrayType;

class BatchRequest
{
    /**
     * @var int[]
     */
    public array $ids;

    /**
     * @var User[]
     */
    public array $users;

    // 使用 ArrayType 注解显式指定类型（优先级高于 @var）
    #[ArrayType(User::class)]
    public array $members;

    // 简单类型也可以使用 PhpType 枚举
    #[ArrayType(\Hyperf\DTO\Type\PhpType::INT)]
    public array $scores;
}
```

#### 请求体为 JSON 数组

控制器方法形参声明为 `array`，配合 `@param` 注解指定元素类型，可实现 JSON 数组的批量映射与逐项验证：

```php
/**
 * @param User[] $users
 */
#[PostMapping(path: 'batch')]
public function batch(#[RequestBody] #[Valid] array $users): array
{
    // $users 为 User[]，每个元素都已验证并映射
}
```

#### 枚举类型

PHP 8.1+ 的 BackedEnum 可直接作为属性类型，映射时自动按值转换：

```php
enum Status: int
{
    case ACTIVE = 1;
    case DISABLED = 0;
}

class UserRequest
{
    public Status $status; // 请求传 1 时自动映射为 Status::ACTIVE
}
```

#### 自定义字段名

```php
namespace App\Request;

use Hyperf\DTO\Annotation\JSONField;

class ApiRequest
{
    // 将请求中的 user_name 映射到 userName，响应序列化时也输出 user_name
    #[JSONField('user_name')]
    public string $userName;

    #[JSONField('user_age')]
    public int $userAge;
}
```

## ✅ 数据验证

> 需要先安装 Hyperf 验证器：`composer require hyperf/validation`

### 内置验证注解

本库提供 90+ 个验证注解（命名空间 `Hyperf\DTO\Annotation\Validation`），与 Laravel 验证规则一一对应，常用的包括：

| 分类 | 注解 |
|------|------|
| 必填 | `Required`、`RequiredIf`、`RequiredUnless`、`RequiredWith`、`RequiredWithAll`、`RequiredWithout`、`RequiredWithoutAll`、`RequiredArrayKeys`、`Present`、`Filled` |
| 类型 | `Integer`、`Numeric`、`Boolean`、`Str`、`Arr`、`File`、`Image`、`Json`、`Decimal` |
| 大小 | `Between`、`Min`、`Max`、`Size`、`Digits`、`DigitsBetween`、`MinDigits`、`MaxDigits`、`MultipleOf`、`Dimensions`（图片尺寸） |
| 格式 | `Email`、`Url`、`ActiveUrl`、`Ip`、`Ipv4`、`Ipv6`、`Date`、`DateEquals`、`DateFormat`、`Uuid`、`Ulid`、`Regex`、`NotRegex`、`MacAddress`、`HexColor`、`Lowercase`、`Uppercase`、`Ascii`、`Timezone` |
| 字符串 | `Alpha`、`AlphaNum`、`AlphaDash`、`StartsWith`、`EndsWith`、`DoesntStartWith`、`DoesntEndWith`、`Contains` |
| 比较 | `Gt`、`Gte`、`Lt`、`Lte`、`Same`、`Different`、`Confirmed`、`Before`、`After`、`BeforeOrEqual`、`AfterOrEqual` |
| 枚举 | `In`、`NotIn`、`InArray`、`Distinct` |
| 文件 | `Mimes`、`Mimetypes`、`Extensions` |
| 数据库 | `Unique`、`Exists`（支持传入 Model 类名自动解析表名） |
| 排除 | `Exclude`、`ExcludeIf`、`ExcludeUnless`、`ExcludeWith`、`ExcludeWithout`、`Prohibits`、`Missing`、`MissingIf`、`MissingUnless`、`MissingWith`、`MissingWithAll` |
| 其他 | `Nullable`、`Sometimes`、`Bail`、`Accepted`、`AcceptedIf`、`Declined`、`Validation`（自定义规则） |

### 使用示例

#### 基本验证

```php
use Hyperf\DTO\Annotation\Validation\Required;
use Hyperf\DTO\Annotation\Validation\Integer;
use Hyperf\DTO\Annotation\Validation\Between;

class DemoQuery
{
    #[Required]
    public string $name;

    #[Required]
    #[Integer]
    #[Between(1, 100)]
    public int $age;
}
```

在控制器中使用 `#[Valid]` 注解启用验证：

```php
#[GetMapping(path: 'query')]
public function query(#[RequestQuery] #[Valid] DemoQuery $request)
{
    // 参数已经验证通过
}
```

#### 自定义错误消息

每个验证注解的最后一个参数为自定义消息：

```php
class UserRequest
{
    #[Required('用户名不能为空')]
    public string $name;

    #[Between(18, 100, '年龄必须在 18-100 之间')]
    public int $age;
}
```

#### 使用 Validation 注解

`Validation` 注解支持 Laravel 风格的验证规则字符串，并可通过 `customKey` 验证数组元素：

```php
use Hyperf\DTO\Annotation\Validation\Validation;

class ComplexRequest
{
    // 使用管道符分隔多个规则
    #[Validation('required|string|min:3|max:50')]
    public string $username;

    // 数组元素验证
    #[Validation('integer', customKey: 'ids.*')]
    public array $ids;
}
```

> ⚠️ **注意**：字符串形式的规则按 `|` 拆分、按 `:` 提取参数，因此规则本身包含 `|` 或 `:` 时（如 `regex:/^(a|b)$/`、`date_format:H:i`）会被错误拆分。涉及正则的规则请使用 `Regex` 专用注解或数组形式。

### 自定义验证规则

继承 `BaseValidation` 类即可创建自定义验证规则：

```php
namespace App\Validation;

use Attribute;
use Hyperf\DTO\Annotation\Validation\BaseValidation;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Phone extends BaseValidation
{
    protected mixed $rule = 'regex:/^1[3-9]\d{9}$/';

    public function __construct(string $messages = '手机号格式不正确')
    {
        parent::__construct($messages);
    }
}
```

使用自定义验证：

```php
use App\Validation\Phone;
use Hyperf\DTO\Annotation\Validation\Required;

class RegisterRequest
{
    #[Required]
    #[Phone]
    public string $mobile;
}
```

## ⚙️ 配置

组件无需配置即可工作。如需定制，创建 `config/autoload/dto.php`（或 `api_docs.php`）：

```php
<?php

use Hyperf\DTO\Type\Convert;

return [
    // 是否启用扫描缓存（生产环境建议 true，配合部署流程预生成代理文件）
    'scan_cacheable' => false,

    // DTO 代理文件生成目录
    'proxy_dir' => BASE_PATH . '/runtime/container/proxy/',

    // 属性默认值级别：
    // 0 - 不注入默认值（jsonSerialize 时用 ?? 默认值兜底，推荐）
    // 1 - 为简单类型属性注入默认值（int=0、string=''、array=[]、bool=false）
    // 2 - 在 1 的基础上，将类类型属性也改为可空并默认 null
    'dto_default_value_level' => 0,

    // 全局响应字段名转换（camel / studly / snake / none / custom）
    'responses_global_convert' => Convert::SNAKE,
];
```

> ⚠️ 注意：`scan_cacheable` 读取的是**顶层**配置键。若使用独立的 `dto.php` 配置文件，请确保该键位于配置根级。

## 🔧 高级功能

### 手动映射

脱离 HTTP 请求场景时，可直接使用 `Mapper` 静态门面：

```php
use Hyperf\DTO\Mapper;

// 数组/对象 → DTO
$user = Mapper::map(['name' => 'tom', 'age' => 20], new User());

// 数组 → DTO 数组
$users = Mapper::mapArray($list, User::class);

// 对象间属性复制（支持 Arrayable 模型）
Mapper::copyProperties($model, new UserResponse());
```

### 响应字段名转换

#### 类级转换

```php
use Hyperf\DTO\Annotation\Dto;
use Hyperf\DTO\Type\Convert;

#[Dto(responseConvert: Convert::SNAKE)]
class UserResponse
{
    public string $userName; // 序列化输出 user_name
    public int $loginCount;  // 序列化输出 login_count
}
```

#### 全局转换

在配置中设置 `responses_global_convert`（见上文「配置」章节），类级 `#[Dto]` 注解优先级更高。

#### 自定义转换

使用 `Convert::CUSTOM` 前需注册转换闭包（如在 `BootApplication` 监听器中）：

```php
use Hyperf\DTO\Type\ConvertCustom;

ConvertCustom::setClosure(fn (string $name) => 'prefix_' . $name);
```

### 事件

组件在启动阶段会派发事件，可监听以扩展行为：

| 事件 | 时机 |
|------|------|
| `Hyperf\DTO\Event\BeforeDtoStart` | 主要用于自动化测试中手动触发 DTO 扫描 |
| `Hyperf\DTO\Event\AfterDtoStart` | 每个 server 的路由扫描完成后派发，携带 server 配置与路由器 |

### RPC 支持

在 JSON-RPC 服务中返回 PHP 对象，需要配置序列化支持。

#### 1. 安装依赖

```bash
composer require symfony/serializer
composer require symfony/property-access
```

#### 2. 配置 Aspect

在 `config/autoload/aspects.php` 中添加：

```php
return [
    \Hyperf\DTO\Aspect\ObjectNormalizerAspect::class,
];
```

#### 3. 配置依赖

在 `config/autoload/dependencies.php` 中添加：

```php
use Hyperf\Serializer\Serializer;
use Hyperf\Serializer\SerializerFactory;

return [
    Hyperf\Contract\NormalizerInterface::class => new SerializerFactory(Serializer::class),
];
```

## 💡 最佳实践

### 1. DTO 类结构设计

- 为不同的请求类型创建独立的 DTO 类
- 使用有意义的类名，如 `CreateUserRequest`、`UpdateUserRequest`
- 将 Request DTO 和 Response DTO 分开存放

### 2. 验证规则

- 优先使用内置验证注解，保持代码可读性
- 复杂验证使用 `Validation` 注解
- 通用验证规则封装为自定义注解

### 3. 错误处理

验证失败会抛出 `Hyperf\Validation\ValidationException` 异常，可以通过异常处理器统一处理：

```php
namespace App\Exception\Handler;

use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;

class ValidationExceptionHandler extends ExceptionHandler
{
    public function handle(\Throwable $throwable, ResponseInterface $response)
    {
        if ($throwable instanceof ValidationException) {
            $this->stopPropagation();
            return $response->withStatus(422)->withBody(
                new SwooleStream(json_encode([
                    'code' => 422,
                    'message' => 'Validation failed',
                    'errors' => $throwable->validator->errors()->toArray(),
                ]))
            );
        }
        return $response;
    }

    public function isValid(\Throwable $throwable): bool
    {
        return $throwable instanceof ValidationException;
    }
}
```

### 4. 生产环境部署

- 设置 `scan_cacheable = true`，在构建/发布阶段执行一次扫描生成代理文件（`runtime/container/proxy/*.dto.proxy.php`），运行时直接加载，减少启动开销
- 代理文件按源文件修改时间自动过期重建（`scan_cacheable = false` 时）

## 📚 常见问题

### Q: 为什么验证没有生效？

A: 请确保：
1. 已安装 `hyperf/validation` 组件
2. 在控制器方法参数上添加了 `#[Valid]` 注解
3. DTO 类中的属性添加了验证注解

### Q: 如何处理嵌套数组？

A: 使用 PHPDoc 或 `ArrayType` 注解：

```php
/**
 * @var User[]
 */
public array $users;

// 或者
#[ArrayType(User::class)]
public array $users;
```

### Q: 可以同时使用 RequestBody 和 RequestFormData 吗？

A: 不可以。这两个注解是互斥的，因为它们处理不同的请求类型，启动扫描阶段会抛出 `DtoException`。

### Q: 如何处理文件上传？

A: 使用 `RequestFormData` 注解，然后通过 `$this->request->file()` 获取文件。

### Q: 嵌套 DTO 的验证规则如何生效？

A: 外层 DTO 验证通过后，组件会递归验证嵌套对象（含对象数组的每个元素），规则取嵌套类属性上的验证注解。嵌套字段为空时跳过递归验证，如需强制必填请在外层属性上加 `#[Required]`。

### Q: DTO 嵌套层级有限制吗？

A: 有，最大嵌套深度为 100，防止循环引用导致无限递归，超限会抛出 `DtoException`。

## 🔗 相关链接

- [Hyperf 官方文档](https://hyperf.wiki)
- [Hyperf Validation](https://hyperf.wiki/3.1/#/zh-cn/validation)
- [PHP Attributes](https://www.php.net/manual/zh/language.attributes.php)
