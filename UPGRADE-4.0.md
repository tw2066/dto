# DTO 4.0 升级说明

## 核心变化

4.0 将输入别名、setter、类型转换和冲突处理统一到 JsonMapper 的类级映射计划：

- DTO 专用入口为 `JsonMapper::simpleMap()`；`Mapper::map()` / `mapArray()` 作为组件门面，按 DTO 字段直接读取输入并映射，不创建整棵 canonical 输入副本。
- 原 `JsonMapper::map()` 不再回退到上游通用映射，调用会抛出 `BadMethodCallException` 并提示使用 `simpleMap()`。
- HTTP `#[Valid]` 请求直接按外部 primaryKey 验证原始输入，验证后由字段映射计划完成 DTO 映射。
- RPC DTO 数组参数直接使用普通字段计划完成对象映射，嵌套 DTO 与 BackedEnum 不依赖 Symfony normalizer；验证直接读取原始数组，非 DTO 参数保持原 denormalize 流程。
- `JSONField` 只保存外部别名与 PHP 属性的映射，继续支持输入别名和响应输出别名。
- 代理只负责响应序列化，不再生成 `_set_dto_alias_*` 输入 setter。

`MappingContext` 和映射计划均为内部实现，不是新的业务编程模型。现有 `Mapper::map()`、`mapArray()`、`copyProperties()` 和普通 PHP DTO 用法不变。

## 必须检查的 breaking change

### 1. 验证规则使用外部 primaryKey

验证规则、messages、attributes 的默认键使用与请求一致的外部 primaryKey。`Same`、`RequiredIf` 等跨字段参数以及 `customKey` 也应引用外部 primaryKey 路径。验证输入仅接受数组，不再自动转换 object 或 `Traversable`；RPC DTO 数组输入也直接使用普通字段计划映射。

```php
#[JSONField('user_name')]
#[Required]
public string $userName;
```

规则键和客户端收到的验证错误键均为 `user_name`；验证器直接读取原始输入，不再经过 canonical 数据转换。`isValidationCustomAttributes=true` 时，现有自定义 attribute 名称继续优先。

### 2. 输入只接受字段主键

普通映射、验证和 RPC 输入只接受字段的 `primaryKey`：存在 `JSONField` 时使用显式别名，否则使用 PHP 属性名。

- 不再接受别名与 PHP 属性名互换输入。
- 不再接受隐式 snake_case、kebab-case、setter 或大小写变体。
- 同时提供主键和其他键时只读取主键；非主键直接忽略，不记录日志，也不再提供异常或 handler 兼容策略。

### 3. 输入别名不再提供隐藏写入通道

主键输入遵循统一访问策略：优先合法业务 setter，其次按现有可见性和 `bIgnoreVisibility` 写属性；static、readonly 和没有可写访问器的 private/protected 属性不会通过旧别名 setter 特殊写入。

若旧代码依赖该行为，请添加正常业务 setter，或显式使用已有的可见性配置。

### 4. 删除内部别名方法约定

`_set_dto_alias_*`、`DtoConfig::getDtoAliasMethodName()`、`isDtoAliasMethodName()` 及 AST 输入 setter 生成已删除。响应 `jsonSerialize()`、输出别名、response convert、默认值和已有自定义序列化不受影响。

### 5. 必须重建代理

升级到 4.0 后，建议清理并重新生成 `runtime/container/proxy/` 中的 DTO 代理。代理缓存只检查文件是否存在及源文件 mtime，不再检查生成器版本标记，因此旧代理不会仅因版本不同而自动失效。

## 保留的行为

- PHP >= 8.1、Hyperf 3.1/3.2 和 JsonMapper 6 的依赖约束不变。
- JsonMapper 的 classMap、class factory、strict null 和 visibility 配置继续有效；missing-data、remove-undefined、postMapping 和 unknown-property handler 不再参与 DTO 映射。
- setter、缩写 setter、继承属性、枚举、ArrayType/docblock、对象数组及 null 元素继续支持；输入键仅接受字段主键。
- 未知字段直接忽略且不记录日志，不为用户原始键建立进程级缓存。
- 单节点最多 1024 个输入键，整棵映射树最多 65536 个输入键，最大嵌套深度 100。
- 嵌套字段未传入或为 null 时跳过递归验证；空数组表示的嵌套对象仍执行子类规则，对象数组中的 null 元素跳过递归验证。普通对象数组与 `mapArray()` 继续保留 null 元素；写入不接受 null 的 variadic setter 时抛出 `JsonMapper_Exception`，不泄漏 PHP `TypeError`。
- `Mapper::map(null, $dto)` 返回默认 DTO；未安装验证器时，`DtoValidation::validate()` 在解析输入前直接 no-op，不触发输入类型或 key 预算异常。
- `Confirmed` 的确认字段遵循主字段 `primaryKey`，例如主键 `password` 对应 `password_confirmation`；验证器直接读取原始输入中的主字段和确认字段。
- `#[Valid]` 数组参数支持标量元素数组和多维 DTO 数组；空数组直接返回，标量元素数组保持原值，多维 DTO 数组按叶子元素验证和映射。
- `classMap` / `classFactories` 的注册键同时支持带或不带前导反斜杠；普通 `map()` / `mapArray()` 继续支持嵌套动态类型。
- variadic setter 继续接受单个标量作为唯一实参。
- 字段主键必须唯一；原始别名与安全别名不一致、别名与另一属性名冲突、多个字段共用别名都会在计划构建时抛出 `DtoException`。
- `customKey` 与跨字段规则使用外部 primaryKey 路径，不再进行 canonical 字段路径转换。

## 验证建议

升级后至少验证：

1. 别名字段的 HTTP 输入、验证错误、响应输出和 RPC 注入。
2. 嵌套 DTO、DTO 数组、setter 声明的数组元素类型。
3. 跨字段规则、`customKey`、非主键输入及未知字段忽略规则。
4. 清理并重建代理后的启动扫描和响应序列化。
5. 业务既有 Mapper 扩展配置及关键请求的回归测试。
