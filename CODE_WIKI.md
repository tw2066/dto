# Hyperf DTO Code Wiki

## 项目概述

**名称**: tangwei/dto  
**描述**: 基于 Hyperf 框架的 DTO（数据传输对象）映射和验证库。  
**版本**: 3.2.x  
**PHP 版本**: >= 8.2  
**许可证**: MIT

---

## 一、项目整体架构

```
┌──────────────────────────────────────────────────────────────────┐
│                        用户控制器层                                │
│          #[RequestBody] #[Valid] CreateRequest $request          │
└──────────────────────────┬───────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────────────┐
│                    AOP 拦截层 (Aspect)                             │
│              CoreMiddlewareAspect                                │
│   ┌──────────────────────┐    ┌──────────────────────────────┐   │
│   │  getInjections       │    │  transferToResponse          │   │
│   │  - 参数注入拦截       │    │  - 响应对象序列化             │   │
│   └──────────┬───────────┘    └──────────────┬───────────────┘   │
└──────────────┼───────────────────────────────┼───────────────────┘
               │                               │
               ▼                               │
┌──────────────────────────────────────────────┼───────────────────┐
│              扫描与管理层 (Scan)              │                   │
│  ┌────────────┐ ┌──────────────┐ ┌──────────┴──────────┐        │
│  │   Scan     │ │PropertyManager│ │ValidationManager    │        │
│  │ - 扫描路由 │ │- 解析属性类型 │ │- 生成验证规则        │        │
│  │ - 触发解析 │ │- 嵌套结构解析 │ │- 存储规则/消息       │        │
│  └────────────┘ └──────────────┘ └─────────────────────┘        │
└───────────────┬─────────────────────────────────────────────────┘
                │
                ▼
┌──────────────────────────────────────────────────────────────────┐
│                    数据映射层 (Mapper)                              │
│  ┌──────────────┐  ┌──────────────────┐  ┌────────────────────┐  │
│  │   Mapper     │  │   JsonMapper     │  │    DtoCommon       │  │
│  │ - 静态入口   │  │ - 反射属性解析   │  │ - 类型判断         │  │
│  │ - copy/map   │  │ - 注解类型推断   │  │ - 命名空间处理     │  │
│  └──────────────┘  └──────────────────┘  └────────────────────┘  │
└──────────────────────────┬───────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────────────┐
│                    验证层 (Validation)                              │
│  ┌──────────────────────────────────────────────────────────┐    │
│  │                   DtoValidation                           │    │
│  │  - 获取验证规则 -> 调用 ValidatorFactory -> 验证数据     │    │
│  │  - 递归验证嵌套对象和数组                                  │    │
│  └──────────────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────────────┘
```

### 启动流程

```
服务器启动
    │
    ├── BootApplication (事件)
    │       └── BootApplicationListener
    │               └── DtoProxyClass::generic()
    │                       ├── 扫描 #[Dto] 和 #[JSONField] 注解
    │                       └── 生成代理类 (支持 jsonSerialize 和 setter)
    │
    └── BeforeServerStart / MainCoroutineServerStart (事件)
            └── BeforeServerListener
                    ├── 获取路由数据
                    └── Scan::scan() 遍历所有控制器方法
                            ├── 解析方法参数注解 (RequestBody, Valid 等)
                            └── 生成属性信息和验证规则
```

---

## 二、目录结构

```
src/
├── Annotation/                        # 注解定义
│   ├── Contracts/                     #   参数来源注解
│   │   ├── RequestBody.php            #     - POST/PUT/PATCH Body 参数
│   │   ├── RequestFormData.php        #     - 表单数据参数
│   │   ├── RequestHeader.php          #     - 请求头参数
│   │   ├── RequestQuery.php           #     - URL 查询参数
│   │   └── Valid.php                  #     - 启用验证
│   ├── Validation/                    #   验证规则注解 (80+)
│   │   ├── BaseValidation.php         #     - 验证基类
│   │   ├── Validation.php             #     - 自定义规则注解
│   │   ├── Required.php               #     - 必填
│   │   ├── Email.php                  #     - 邮箱
│   │   └── ... (更多验证规则)
│   ├── Dto.php                        #   DTO 类注解 (响应转换配置)
│   ├── JSONField.php                  #   字段别名注解
│   └── ArrayType.php                  #   数组元素类型注解
│
├── Aspect/                            # AOP 切面
│   ├── CoreMiddlewareAspect.php       #   核心中间件切面 (参数注入 + 响应)
│   └── ObjectNormalizerAspect.php     #   Symfony Serializer 适配切面
│
├── Ast/                               # AST 代理类生成
│   ├── DtoProxyClass.php              #   代理类生成器
│   ├── DtoVisitor.php                 #   PHP Parser AST 访问器
│   └── PropertyInfo.php               #   属性信息模型
│
├── Event/                             # 自定义事件
│   ├── BeforeDtoStart.php             #   DTO 初始化前事件
│   └── AfterDtoStart.php              #   DTO 初始化后事件
│
├── Exception/                         # 异常
│   └── DtoException.php               #   DTO 自定义异常
│
├── Router/                            # RPC 路由
│   ├── JsonRpcHttpRouter.php          #   JSON-RPC HTTP 路由
│   └── JsonRpcTcpRouter.php           #   JSON-RPC TCP 路由
│
├── Scan/                              # 扫描与管理
│   ├── Scan.php                       #   总扫描入口
│   ├── MethodParameter.php            #   方法参数信息
│   ├── MethodParametersManager.php    #   方法参数管理器
│   ├── Property.php                   #   属性信息模型
│   ├── PropertyManager.php            #   属性管理器
│   ├── PropertyEnum.php               #   枚举类型处理
│   └── ValidationManager.php          #   验证规则管理器
│
├── Type/                              # 类型转换
│   ├── Convert.php                    #   转换枚举 (CAMEL/SNAKE/STUDLY/NONE/CUSTOM)
│   ├── ConvertCustom.php              #   自定义转换接口
│   └── PhpType.php                    #   PHP 类型包装
│
├── ApiAnnotation.php                  # 注解元数据查询工具
├── BeforeServerListener.php           # 服务器启动前监听器
├── BootApplicationListener.php        # 应用启动监听器
├── ConfigProvider.php                 # Hyperf 配置提供者
├── DtoCommon.php                      # 通用工具类
├── DtoConfig.php                      # DTO 配置管理
├── DtoValidation.php                  # DTO 验证器
├── JsonMapper.php                     # JSON 映射核心 (扩展 JsonMapper)
└── Mapper.php                         # 静态映射入口
```

---

## 三、核心模块职责

### 3.1 配置与启动模块

#### ConfigProvider [`src/ConfigProvider.php`](src/ConfigProvider.php)

Hyperf 组件配置入口，定义:
- **Aspects**: 注册 `CoreMiddlewareAspect` 切面
- **Listeners**: 注册 `BeforeServerListener` 监听器
- **Annotations**: 扫描注解路径
- **Publish**: 无发布配置

#### DtoConfig [`src/DtoConfig.php`](src/DtoConfig.php)

DTO 全局配置管理器:
| 属性/方法 | 说明 |
|----------|------|
| `proxy_dir` | 代理类生成目录，默认 `BASE_PATH/runtime/container/proxy/` |
| `scan_cacheable` | 是否启用扫描缓存 |
| `dto_default_value_level` | DTO 默认值处理级别 (0/1/2) |
| `responses_global_convert` | 全局响应字段转换策略 (驼峰/蛇形等) |
| `getScanHandler()` | 获取扫描处理器 (ProcScanHandler/PcntlScanHandler) |

#### BeforeServerListener [`src/BeforeServerListener.php`](src/BeforeServerListener.php)

监听服务器启动事件，执行核心扫描逻辑:
1. 获取路由器实例 (支持 HTTP/TCP/JSON-RPC)
2. 遍历所有路由 Handler
3. 调用 `Scan::scan()` 解析控制器方法
4. 分发 `AfterDtoStart` 事件

#### BootApplicationListener [`src/BootApplicationListener.php`](src/BootApplicationListener.php)

监听 `BootApplication` 事件 (优先级 999)，触发 `DtoProxyClass::generic()` 生成代理类。

---

### 3.2 扫描模块 (Scan)

#### Scan [`src/Scan/Scan.php`](src/Scan/Scan.php)

扫描入口类，负责:
- 解析控制器方法的参数定义
- 递归扫描 DTO 类的属性
- 触发验证规则生成

```php
public function scan(string $className, string $methodName): void
```

#### MethodParametersManager [`src/Scan/MethodParametersManager.php`](src/Scan/MethodParametersManager.php)

管理方法参数信息:
- **setMethodParameters()**: 解析方法参数上的注解 (RequestBody, RequestQuery, Valid 等)
- **scanClassMethodParam()**: 扫描参数类型，支持简单类型、数组、嵌套类、枚举
- 验证注解互斥性 (RequestBody 和 RequestFormData 不能共存)

#### PropertyManager [`src/Scan/PropertyManager.php`](src/Scan/PropertyManager.php)

解析 DTO 类属性:
- 递归扫描类属性，最大深度 100 层
- 支持 PHPDoc `@var` 类型注解
- 支持 `#[JSONField]` 字段别名
- 支持 `#[ArrayType]` 数组元素类型
- 支持枚举类型 (PropertyEnum)

#### ValidationManager [`src/Scan/ValidationManager.php`](src/Scan/ValidationManager.php)

生成和管理验证规则:
- 从属性注解中提取验证规则
- 支持管道符分隔的多规则 (`"required|string|min:3"`)
- 存储 rules, messages, attributes 三部分验证数据

---

### 3.3 映射模块 (Mapper)

#### Mapper [`src/Mapper.php`](src/Mapper.php)

静态入口类，提供数据映射方法:

| 方法 | 说明 |
|------|------|
| `map(mixed $json, object $object)` | 将数据映射到对象 |
| `copyProperties(mixed $source, object $target)` | 从源数据复制到目标对象 |
| `mapArray(mixed $json, string $className)` | 将数组数据映射为对象数组 |
| `getJsonMapper(string $key)` | 获取或创建 JsonMapper 实例 |

#### JsonMapper [`src/JsonMapper.php`](src/JsonMapper.php)

扩展自 `netresearch/jsonmapper` 的 `\JsonMapper` 类:

- **inspectProperty()**: 重写属性检查逻辑，优先使用 DTO 别名方法，支持 PHP 8 类型推断
- **parseAnnotationsNew()**: 使用 `phpDocumentor` 解析 PHPDoc，支持 `#[ArrayType]` 注解覆盖

#### DtoCommon [`src/DtoCommon.php`](src/DtoCommon.php)

通用工具方法:
- **getTypeName()**: 获取反射属性/参数的类型名称
- **isSimpleType()**: 判断是否为 PHP 简单类型
- **getFullNamespace()**: 获取完整命名空间
- **isArrayOfType()**: 判断是否为数组类型
- **getSafeName()**: 获取安全的属性名

---

### 3.4 验证模块 (Validation)

#### DtoValidation [`src/DtoValidation.php`](src/DtoValidation.php)

DTO 数据验证核心:

| 方法 | 说明 |
|------|------|
| `validate(string $className, $data)` | 入口验证方法 |
| `validateResolved(string $className, $data)` | 执行验证 + 递归验证嵌套对象 |
| `buildValidationException()` | 构建验证失败异常 |

**递归验证逻辑**:
1. 获取类的验证规则数组
2. 调用 Hyerf ValidatorFactory 执行验证
3. 遍历非简单类型属性，递归验证嵌套对象/数组元素
4. 验证失败抛出 `ValidationException`

#### BaseValidation [`src/Annotation/Validation/BaseValidation.php`](src/Annotation/Validation/BaseValidation.php)

所有验证注解的基类:
- `$rule`: 验证规则
- `$messages`: 自定义错误消息
- `$customKey`: 自定义字段键 (用于嵌套验证，如 `'items.*'`)
- `$fieldName`: 字段名称

#### Validation [`src/Annotation/Validation/Validation.php`](src/Annotation/Validation/Validation.php)

Laravel 风格的自定义验证注解:

```php
#[Validation("required|string|min:3|max:50")]
public string $username;

#[Validation("integer", customKey: 'ids.*')]
public array $ids;
```

---

### 3.5 AOP 切面模块

#### CoreMiddlewareAspect [`src/Aspect/CoreMiddlewareAspect.php`](src/Aspect/CoreMiddlewareAspect.php)

拦截 Hyperf 核心中间件的两个方法:

**1. getInjections** - 参数注入拦截:
- 遍历方法参数定义
- 根据注解类型 (RequestBody/RequestQuery/RequestHeader/RequestFormData) 从请求中提取数据
- 如果标记了 `#[Valid]`，先执行验证
- 调用 `Mapper::map()` 将数据映射到 DTO 对象
- 支持数组类型参数 (`array $data`) 的批量映射

**2. transferToResponse** - 响应序列化拦截:
- 处理字符串、数组、Arrayable、Jsonable、普通对象等不同类型的响应
- 统一设置 Content-Type 和响应体

#### ObjectNormalizerAspect [`src/Aspect/ObjectNormalizerAspect.php`](src/Aspect/ObjectNormalizerAspect.php)

适配 Symfony Serializer 的 `denormalize` 方法，用于 RPC 场景的序列化支持:
- 拦截 `Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer::denormalize`
- 使用本库的 `Mapper::map()` 替代原生反序列化

---

### 3.6 AST 代理类生成模块

#### DtoProxyClass [`src/Ast/DtoProxyClass.php`](src/Ast/DtoProxyClass.php)

在应用启动时生成代理类:
1. 扫描所有标注 `#[Dto]` 和 `#[JSONField]` 的类
2. 使用 `PhpParser` 解析原始类源码
3. 通过 `DtoVisitor` 修改 AST 树
4. 生成包含 `jsonSerialize()` 方法和别名 setter 的代理类

**代理类功能**:
- 实现 `JsonSerializable` 接口
- 根据 `#[JSONField]` 生成私有 setter 方法 (用于字段别名映射)
- 根据 `#[Dto(Convert::xxx)]` 生成字段名转换 (驼峰/蛇形)
- 支持默认值处理 (`dto_default_value_level`)

#### DtoVisitor [`src/Ast/DtoVisitor.php`](src/Ast/DtoVisitor.php)

PHP Parser AST 访问器:
- **leaveNode()**: 为属性设置默认值 (根据类型: int=0, string='', array=[], bool=false)
- **afterTraverse()**: 
  - 为 `#[JSONField]` 字段生成私有 setter 方法
  - 生成 `jsonSerialize()` 方法实现
- **createSetter()**: 创建形如 `_set_dto_alias_{md5(alias)}(param)` 的私有方法
- **createJsonSerialize()**: 创建返回数组的序列化方法

---

## 四、注解系统详解

### 4.1 参数来源注解

命名空间: `Hyperf\DTO\Annotation\Contracts`

| 注解 | 目标 | 说明 |
|------|------|------|
| `#[RequestBody]` | Parameter | 获取 POST/PUT/PATCH 的 Body 参数 |
| `#[RequestQuery]` | Parameter | 获取 URL 查询参数 (GET) |
| `#[RequestFormData]` | Parameter | 获取表单数据 (multipart/form-data) |
| `#[RequestHeader]` | Parameter | 获取请求头信息 |
| `#[Valid]` | Parameter | 启用数据验证 (需配合参数来源注解使用) |

**使用规则**:
- `RequestBody` 和 `RequestFormData` 互斥，不能在同一方法中使用
- `RequestHeader` 同一方法只能有一个

### 4.2 DTO 类注解

| 注解 | 目标 | 说明 |
|------|------|------|
| `#[Dto(responseConvert: Convert::SNAKE)]` | Class | 配置响应字段转换策略 |
| `#[JSONField('user_name')]` | Property | 定义请求/响应字段别名 |
| `#[ArrayType(User::class)]` | Property | 显式指定数组元素类型 |

### 4.3 验证注解

命名空间: `Hyperf\DTO\Annotation\Validation`

#### 存在性验证
| 注解 | 参数 | 说明 |
|------|------|------|
| `Required` | `$messages` | 必填字段 |
| `Nullable` | `$messages` | 允许为空 |
| `Filled` | `$messages` | 非空但可为空字符串 |
| `Missing` | `$messages` | 必须不存在 |
| `Present` | `$messages` | 必须存在 |

#### 条件验证
| 注解 | 参数 | 说明 |
|------|------|------|
| `RequiredIf` | `$field, $values, $messages` | 当某字段为某值时必填 |
| `RequiredUnless` | `$field, $values, $messages` | 当某字段不为某值时必填 |
| `RequiredWith` | `$fields, $messages` | 当某些字段存在时必填 |
| `RequiredWithout` | `$fields, $messages` | 当某些字段不存在时必填 |
| `ExcludeIf` | `$field, $values` | 当某字段为某值时排除 |
| `ExcludeUnless` | `$field, $values` | 当某字段不为某值时排除 |

#### 字符串验证
| 注解 | 参数 | 说明 |
|------|------|------|
| `Str` | `$messages` | 字符串类型 |
| `Email` | `$messages` | 邮箱格式 |
| `Url` | `$messages` | URL 格式 |
| `Alpha` | `$messages` | 纯字母 |
| `AlphaNum` | `$messages` | 字母+数字 |
| `AlphaDash` | `$messages` | 字母+数字+破折号+下划线 |
| `Regex` | `$pattern, $messages` | 正则表达式匹配 |
| `StartsWith` | `$values, $messages` | 以某值开头 |
| `EndsWith` | `$values, $messages` | 以某值结尾 |
| `DoesntStartWith` | `$values, $messages` | 不以某值开头 |
| `DoesntEndWith` | `$values, $messages` | 不以某值结尾 |
| `Contains` | `$values, $messages` | 包含某值 |
| `Lowercase` | `$messages` | 全小写 |
| `Uppercase` | `$messages` | 全大写 |
| `HexColor` | `$messages` | 十六进制颜色 |
| `Uuid` | `$messages` | UUID 格式 |
| `Ulid` | `$messages` | ULID 格式 |
| `MacAddress` | `$messages` | MAC 地址格式 |
| `Timezone` | `$messages` | 时区格式 |
| `Json` | `$messages` | JSON 格式 |
| `Ascii` | `$messages` | ASCII 字符 |

#### 数字验证
| 注解 | 参数 | 说明 |
|------|------|------|
| `Integer` | `$messages` | 整数 |
| `Numeric` | `$messages` | 数字 |
| `Decimal` | `$places, $messages` | 指定位数小数 |
| `Between` | `$min, $max, $messages` | 数值范围 |
| `Min` / `Max` | `$value, $messages` | 最小/最大值 |
| `Gt` / `Gte` | `$field, $messages` | 大于/大于等于某字段 |
| `Lt` / `Lte` | `$field, $messages` | 小于/小于等于某字段 |
| `MultipleOf` | `$value, $messages` | 某值的倍数 |
| `Size` | `$size, $messages` | 大小相等 |

#### 长度验证
| 注解 | 参数 | 说明 |
|------|------|------|
| `Digits` | `$value, $messages` | 固定位数 |
| `DigitsBetween` | `$min, $max, $messages` | 位数范围 |
| `MinDigits` / `MaxDigits` | `$value, $messages` | 最小/最大位数 |

#### 数组验证
| 注解 | 参数 | 说明 |
|------|------|------|
| `Arr` | `$messages` | 数组类型 |
| `ListRule` | `$messages` | 列表 (连续索引数组) |
| `InArray` | `$field, $messages` | 在某字段数组中 |
| `Distinct` | `$mode, $messages` | 无重复值 |

#### 集合验证
| 注解 | 参数 | 说明 |
|------|------|------|
| `In` | `$values, $messages` | 在指定值集合中 |
| `NotIn` | `$values, $messages` | 不在指定值集合中 |
| `Different` | `$field, $messages` | 与某字段不同 |
| `Same` | `$field, $messages` | 与某字段相同 |
| `Confirmed` | `$messages` | 需要确认字段 (如 password_confirmation) |

#### 日期验证
| 注解 | 参数 | 说明 |
|------|------|------|
| `Date` | `$messages` | 日期格式 |
| `DateFormat` | `$format, $messages` | 指定日期格式 |
| `DateEquals` | `$date, $messages` | 日期等于 |
| `After` / `AfterOrEqual` | `$date, $messages` | 在日期之后/之后或等于 |
| `Before` / `BeforeOrEqual` | `$date, $messages` | 在日期之前/之前或等于 |

#### 文件验证
| 注解 | 参数 | 说明 |
|------|------|------|
| `File` | `$messages` | 文件类型 |
| `Image` | `$messages` | 图片文件 |
| `Mimes` | `$mimes, $messages` | MIME 类型 |
| `Mimetypes` | `$mimes, $messages` | 精确 MIME 类型 |
| `Size` | `$size, $messages` | 文件大小 (KB) |
| `Dimensions` | 规则字符串, `$messages` | 图片尺寸 |
| `Extensions` | `$extensions, $messages` | 文件扩展名 |

#### 数据库验证
| 注解 | 参数 | 说明 |
|------|------|------|
| `Exists` | `$table, $column` | 数据库中存在 |
| `Unique` | `$table, $column` | 数据库唯一 |

#### 控制流
| 注解 | 说明 |
|------|------|
| `Bail` | 验证失败后停止后续验证 |
| `Sometimes` | 仅在字段存在时验证 |

---

## 五、类型转换系统

### Convert 枚举 [`src/Type/Convert.php`](src/Type/Convert.php)

| 值 | 说明 | 示例 |
|----|------|------|
| `CAMEL` | 驼峰命名 | `user_name` -> `userName` |
| `STUDLY` | 大驼峰 | `user_name` -> `UserName` |
| `SNAKE` | 蛇形命名 | `userName` -> `user_name` |
| `NONE` | 不转换 | 原样输出 |
| `CUSTOM` | 自定义转换 | 通过 `ConvertCustom` 闭包 |

### 使用方式

```php
// 类级别
#[Dto(responseConvert: Convert::SNAKE)]
class UserResponse { ... }

// 全局配置 (config/autoload/dto.php)
return [
    'responses_global_convert' => Convert::SNAKE,
];
```

### ConvertCustom 接口 [`src/Type/ConvertCustom.php`](src/Type/ConvertCustom.php)

```php
interface ConvertCustom
{
    public function convert(mixed $value): mixed;
    public static function getClosure(): callable;
}
```

---

## 六、依赖关系

### 运行时依赖

| 包 | 版本 | 用途 |
|----|------|------|
| `php` | >= 8.1 | 语言版本要求 |
| `netresearch/jsonmapper` | ~5.0.0 | JSON 数据映射核心 |
| `hyperf/http-server` | ~3.1.0\|~3.2.0 | HTTP 服务器组件 |
| `hyperf/di` | ~3.1.0\|~3.2.0 | 依赖注入和注解系统 |
| `hyperf/validation` | ~3.1.0\|~3.2.0 | 数据验证器 |
| `phpdocumentor/reflection-docblock` | ^6.0 | PHPDoc 解析 |
| `nikic/php-parser` | ^4.19\|^5.6 | PHP AST 解析和生成 |
| `symfony/finder` | ^6.0\|^7.0 | 文件系统查找 |

### 可选依赖 (dev)

| 包 | 用途 |
|----|------|
| `symfony/serializer` + `symfony/property-access` | RPC 序列化支持 (需手动配置 ObjectNormalizerAspect) |

---

## 七、关键类与函数说明

### 7.1 数据流核心路径

#### HTTP 请求处理流程

```
客户端请求
    │
    ▼
Hyperf CoreMiddleware
    │
    ▼ 触发 CoreMiddlewareAspect
    │
    ├── getInjections 拦截
    │       │
    │       ├── 1. 获取 MethodParameter (从 MethodParametersManager)
    │       │       └── 判断参数注解类型: RequestBody/Query/Header/FormData
    │       │
    │       ├── 2. 从请求对象提取数据
    │       │       └── $request->getParsedBody() / getQueryParams() / getHeaders()
    │       │
    │       ├── 3. 验证 (如果标记 #[Valid])
    │       │       └── DtoValidation::validate()
    │       │           ├── 获取 ValidationManager 中的规则
    │       │           └── 递归验证嵌套对象
    │       │
    │       └── 4. 数据映射
    │               └── Mapper::map($param, make($className))
    │                   └── JsonMapper::map() (基于反射和注解推断类型)
    │
    ▼
控制器方法执行 (注入已填充的 DTO 对象)
    │
    ▼
返回响应
    │
    ▼ 触发 transferToResponse 拦截
    │
    └── CoreMiddlewareAspect::transferToResponse()
            └── 根据类型 (string/array/Arrayable/Jsonable/object) 序列化响应
```

### 7.2 关键函数说明

#### Mapper::map()

```php
public static function map(mixed $json, object $object): object
```

将 JSON/数组数据映射到 DTO 对象实例。底层使用 `JsonMapper` 通过反射和注解推断属性类型，支持:
- 简单类型自动转换 (int, string, bool, float)
- 嵌套对象实例化和映射
- 数组元素映射 (`User[]`)
- 枚举类型映射
- `#[JSONField]` 字段别名映射

#### CoreMiddlewareAspect::getInjections()

```php
private function getInjections(array $definitions, string $callableName, array $arguments, $coreMiddleware): array
```

拦截 Hyperf 依赖注入流程:
1. 遍历方法参数定义
2. 对于标记了 DTO 注解的参数:
   - 从请求中获取对应数据源
   - 执行验证 (如果标记 #[Valid])
   - 映射到 DTO 对象
3. 对于数组类型参数，支持批量映射
4. 对于已注入的值 (RPC 场景)，执行验证后反序列化

#### Scan::scan()

```php
public function scan(string $className, string $methodName): void
```

服务器启动时调用，扫描控制器方法:
1. 解析方法参数上的注解
2. 扫描参数类型 (简单类型、类、数组、枚举)
3. 递归扫描嵌套类的属性
4. 生成验证规则

#### ValidationManager::generateValidation()

```php
public function generateValidation(string $className, string $fieldName): void
```

为 DTO 属性生成验证规则:
1. 获取属性上的所有注解
2. 提取继承自 `BaseValidation` 的验证注解
3. 解析规则字符串 (支持管道符分隔)
4. 处理 `#[JSONField]` 别名映射
5. 存储 rules / messages / attributes

---

## 八、事件系统

| 事件 | 触发时机 | 用途 |
|------|----------|------|
| `BeforeDtoStart` | DTO 扫描前 | 可用于在扫描前执行初始化 |
| `AfterDtoStart` | DTO 扫描完成后 | 可用于获取扫描后的路由信息 |

---

## 九、RPC 支持

### JSON-RPC HTTP/TCP

通过 `JsonRpcHttpRouter` 和 `JsonRpcTcpRouter` 提供支持:
- `BeforeServerListener` 自动检测 JSON-RPC 服务器类型
- 对 RPC 调用的参数进行验证和映射
- 对 RPC 返回的 DTO 对象进行序列化

### 序列化配置

需要额外安装和配置:

```bash
composer require symfony/serializer ^5.0|^6.0
composer require symfony/property-access ^5.0|^6.0
```

配置 `config/autoload/aspects.php`:
```php
return [
    \Hyperf\DTO\Aspect\ObjectNormalizerAspect::class,
];
```

---

## 十、项目运行方式

### 安装

```bash
composer require tangwei/dto
```

安装后组件自动注册，无需额外配置。

### 运行测试

```bash
composer test
# 或
phpunit -c phpunit.xml --colors=always
```

### 代码分析

```bash
composer analyse
# 或
phpstan analyse --memory-limit 1024M -l 0 ./src
```

### 代码风格检查

```bash
composer cs-fix
# 或
php-cs-fixer fix src && php-cs-fixer fix tests
```

---

## 十一、设计模式与技术亮点

| 技术 | 说明 |
|------|------|
| **AOP 切面编程** | 通过 `CoreMiddlewareAspect` 拦截核心流程，无侵入式实现 DTO 绑定 |
| **AST 代理类生成** | 使用 `PhpParser` 在启动时生成代理类，实现 `JsonSerializable` 和别名 setter |
| **反射 + PHPDoc** | 结合 PHP 8 类型系统和 phpDocumentor 实现精确的类型推断 |
| **注解驱动** | 全库基于 PHP 8 Attributes，代码简洁声明式 |
| **递归扫描** | 支持嵌套对象和数组的自动解析和验证，最大深度 100 层 |
| **关联数组优化** | 使用关联数组替代 `in_array` 实现 O(1) 检查，提升扫描性能 |
| **延迟加载** | `ScanHandler` 根据环境选择 `PcntlScanHandler` 或 `ProcScanHandler` |
| **验证规则缓存** | 扫描结果静态缓存，避免重复解析 |

---

## 十二、扩展指南

### 自定义验证规则

继承 `BaseValidation` 类:

```php
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Phone extends BaseValidation
{
    protected $rule = 'regex:/^1[3-9]\d{9}$/';
    
    public function __construct(string $messages = '手机号格式不正确')
    {
        parent::__construct($messages);
    }
}
```

### 自定义类型转换

实现 `ConvertCustom` 接口:

```php
class CustomConvert implements ConvertCustom
{
    public function convert(mixed $value): mixed
    {
        // 自定义转换逻辑
        return $value;
    }
}
```

### 配置选项

在 `config/autoload/dto.php` 中:

```php
return [
    'proxy_dir' => '/path/to/proxy',          // 代理类目录
    'scan_cacheable' => true,                 // 扫描缓存
    'dto_default_value_level' => 0,           // 默认值处理级别 (0/1/2)
    'responses_global_convert' => null,       // 全局响应转换策略
];
```
