# DTO 4.0 升级说明

## 核心变化

4.0 将输入别名、setter、类型转换和冲突处理统一到 JsonMapper 的类级映射计划：

- 普通 `Mapper::map()` / `mapArray()` 按 DTO 字段直接读取输入并映射，不创建整棵 canonical 输入副本。
- HTTP `#[Valid]` 请求通过一次 `ResolvedInput` 解析结果完成验证和映射。
- RPC DTO 参数直接消费同一 `ResolvedInput` 完成对象映射，嵌套 DTO 与 BackedEnum 不依赖 Symfony normalizer；非 DTO 参数保持原 denormalize 流程。
- `JSONField` 只保存外部别名与 PHP 属性的映射，继续支持输入别名和响应输出别名。
- 代理只负责响应序列化，不再生成 `_set_dto_alias_*` 输入 setter。

`resolveInput()`、`mapResolvedInput()`、`MappingContext` 和映射计划均为内部实现，不是新的业务编程模型。现有 `Mapper::map()`、`mapArray()`、`copyProperties()` 和普通 PHP DTO 用法不变。

## 必须检查的 breaking change

### 1. 验证内部字段名改为 PHP 属性名

验证规则、messages、attributes 的默认键统一使用 PHP 属性名。`Same`、`RequiredIf` 等跨字段参数以及 `customKey` 也应引用 PHP 属性路径，而不是外部别名。

```php
#[JSONField('user_name')]
#[Required]
public string $userName;
```

内部规则键是 `userName`；客户端收到的验证错误键仍是 `user_name`。`isValidationCustomAttributes=true` 时，现有自定义 attribute 名称继续优先。

### 2. 重复等价输入键按优先级取值

同一字段同时收到属性名、别名或兼容的合法变体时不再拒绝，按以下优先级取值：

1. 显式 `JSONField` 别名（外部契约）
2. PHP 属性名
3. setter/大小写等兼容变体；同为变体时保留先出现的值

校验和后续映射始终消费同一选中值，未选中的候选值不会写入对象。

不可达的 `InputConflictException` 及其 catch/translate 链路已移除，不影响上述重复键优先级语义。

未注册的任意大小写变体仍按未知字段处理，不会新增大小写猜测。

### 3. 输入别名不再提供隐藏写入通道

别名和属性名输入现在遵循同一访问策略：优先合法业务 setter，其次按现有可见性和 `bIgnoreVisibility` 写属性；static、readonly 和没有可写访问器的 private/protected 属性不会通过旧别名 setter 特殊写入。

若旧代码依赖该行为，请添加正常业务 setter，或显式使用已有的可见性配置。

### 4. 删除内部别名方法约定

`_set_dto_alias_*`、`DtoConfig::getDtoAliasMethodName()`、`isDtoAliasMethodName()` 及 AST 输入 setter 生成已删除。响应 `jsonSerialize()`、输出别名、response convert、默认值和已有自定义序列化不受影响。

### 5. 必须重建代理

升级到 4.0 后，清理并重新生成 `runtime/container/proxy/` 中的 DTO 代理。当前代理带有 4.0 生成器版本标识；旧版本不会被 scanned classmap 接受，也不能仅依赖 `scan_cacheable=true` 继续复用。

## 保留的行为

- PHP >= 8.1、Hyperf 3.1/3.2 和 JsonMapper 6 的依赖约束不变。
- JsonMapper 的 classMap、class factory、post-mapping、unknown-property handler、strict null 和 visibility 配置继续有效。
- setter、缩写 setter、合法连字符/下划线变体、继承属性、枚举、ArrayType/docblock、对象数组及 null 元素继续支持。
- 未知字段默认忽略，异常/handler 策略保留；不为用户原始键建立进程级缓存。
- 单节点最多 1024 个输入键，整棵映射树最多 65536 个输入键，最大嵌套深度 100；未知字段日志最多 32 条。
- 嵌套字段未传入或为 null 时跳过递归验证；空数组表示的嵌套对象仍执行子类规则，对象数组中的 null 元素跳过递归验证。普通对象数组与 `mapArray()` 继续保留 null 元素；写入不接受 null 的 variadic setter 时抛出 `JsonMapper_Exception`，不泄漏 PHP `TypeError`。
- `Mapper::map(null, $dto)` 返回默认 DTO；未安装验证器时，`DtoValidation::validate()` 在解析输入前直接 no-op，不触发输入类型或 key 预算异常。
- `Confirmed` 的确认字段遵循主字段外部别名，例如 `password` / `password_confirmation` 会映射到 canonical `pwd` / `pwd_confirmation` 后验证；PHP 属性名形式同样可用。
- `#[Valid]` 数组参数支持标量元素数组和多维 DTO 数组；空数组直接返回，标量元素数组保持原值，多维 DTO 数组按叶子元素验证和映射。
- `classMap` / `classFactories` 的注册键同时支持带或不带前导反斜杠；resolved 路径复用工厂选定的具体类型，不为同一嵌套值重复调用工厂。
- variadic setter 继续接受单个标量作为唯一实参。
- 别名与另一属性名冲突时按既定优先级确定性选择；同优先级的真实声明歧义仍在计划构建时拒绝。
- 旧版本按 `JSONField` 别名书写的 `customKey` 会自动转换为 canonical PHP 属性路径，并同步转换 messages/attributes 的键前缀；新代码仍推荐使用 PHP 属性路径。

## 验证建议

升级后至少验证：

1. 别名字段的 HTTP 输入、验证错误、响应输出和 RPC 注入。
2. 嵌套 DTO、DTO 数组、setter 声明的数组元素类型。
3. 跨字段规则、`customKey`、重复等价键及未知字段策略。
4. 清理并重建代理后的启动扫描和响应序列化。
5. 业务既有 Mapper 扩展配置及关键请求的回归测试。
