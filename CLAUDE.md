# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 项目概述

`tangwei/dto` — 基于 Hyperf ~3.2 的 DTO 映射与验证 **Composer 组件库**（非应用）。命名空间 `Hyperf\DTO\`，要求 PHP >= 8.2。通过 `ConfigProvider` 被 Hyperf 自动发现注册，无需消费方配置。

## 常用命令

```bash
# 安装依赖
php composer install

# 全部测试（phpunit）
php composer test

# 单个测试
php vendor/bin/phpunit -c phpunit.xml --filter ScanAnnotationTest
php vendor/bin/phpunit -c phpunit.xml tests/JsonMapperTest.php

# 静态分析（phpstan level 0，仅分析 src/，phpstan.neon 中排除了 5 个文件）
php composer analyse

# 代码风格修复（php-cs-fixer，规则集 @PSR2 + @Symfony + @PhpCsFixer）
php composer cs-fix
```

CI（`.github/workflows/test.yml`）在 hyperf/hyperf 8.2/8.3/ swoole 容器矩阵中执行 `composer analyse` + `composer test`。

## 核心架构：启动扫描 + 运行时拦截

组件分两个阶段，两阶段通过 Scan 管理器中的 **PHP 静态数组** 传递元数据（`PropertyManager::$content`、`ValidationManager::$content`、`MethodParametersManager::$content`）—— 启动时填充，请求时只读。因此单元测试里必须手动触发扫描（派发 `BeforeDtoStart` 事件或直接调用 `Scan`/`PropertyManager`），否则 AOP 拦截逻辑拿不到任何元数据。

### 启动阶段

1. **代理类生成**：`BootApplicationListener`（BootApplication 事件）→ `Ast/DtoProxyClass::generic()`：用 nikic/php-parser 为带 `#[Dto]` 或 `#[JSONField]` 注解的类生成 `runtime/container/proxy/*.dto.proxy.php` —— 注入 `jsonSerialize()`（响应字段名转换）和私有别名 setter（`_set_dto_alias_<md5(字段名)>`）。已扫描则仅注册 classmap 后返回；生成完毕后 `exit()` 是正常流程（Hyperf 扫描进程模式）。
2. **路由扫描**：`BeforeServerListener`（BeforeServerStart / MainCoroutineServerStart 事件）遍历三种路由（HTTP `DispatcherFactory`、JSON-RPC TCP、JSON-RPC HTTP），对每个控制器方法调用 `Scan\Scan::scan()`，填充：
   - `MethodParametersManager` — 方法参数的 `#[RequestBody]/#[RequestQuery]/#[RequestFormData]/#[RequestHeader]/#[Valid]` 元数据；互斥约束在此校验（同一参数只能标一个来源注解、同一方法 RequestBody 与 RequestFormData 不共存、RequestHeader 唯一），违反即在启动期抛 `DtoException`
   - `PropertyManager` — 递归解析 DTO 属性类型（简单类型 / 嵌套类 / `@var` 或 `#[ArrayType]` 数组元素类型 / 枚举 / JSONField 别名），最大嵌套深度 100（`MAX_SCAN_DEPTH`）
   - `ValidationManager` — 从属性上的 `BaseValidation` 子类注解收集 Laravel 风格验证规则、自定义消息、JSONField 别名

### 运行时阶段

`Aspect/CoreMiddlewareAspect` 拦截 `CoreMiddleware` 两个方法：

- `getInjections` — 控制器参数注入：按扫描元数据从请求对应来源（body/query/formData/header）取数据 → 标了 `#[Valid]` 则经 `DtoValidation` 验证（失败抛 `ValidationException`，并递归验证嵌套对象与对象数组）→ `Mapper::map()` 映射为 DTO。也支持 `array` 形参 + `@param User[]` docblock 的 JSON 数组批量映射（RPC 场景走 `isValid` 分支）。
- `transferToResponse` — 对象/数组响应统一序列化为 JSON 输出。

### 数据映射与 AST 代理的协作

`Mapper`（静态门面）→ `JsonMapper`（继承 `netresearch/jsonmapper`，扩展了 `inspectProperty`）：属性类型推断优先级为 `#[ArrayType]` 注解 > phpdocumentor 解析 `@var`（用 ContextFactory 正确解析命名空间）> PHP 原生类型。`#[JSONField('user_name')]` 别名映射依赖代理类生成的私有 setter —— **修改 `DtoConfig::getDtoAliasMethodName()` 的命名规则会同时影响 `DtoVisitor`（生成端）和 `JsonMapper`（消费端），两侧必须同步**。

### 响应字段名转换

`#[Dto(responseConvert: Convert::SNAKE)]`（类级，优先）或全局配置 `responses_global_convert` → 代理类 `jsonSerialize()` 按 `Type/Convert` 枚举（camel/studly/snake/none/custom）输出键名。`dto_default_value_level`（0/1/2）控制是否为无默认值属性注入类型默认值。

## 配置键的坑

消费方配置文件为 `config/autoload/dto.php`（缺失时回退读 `api_docs` 键）。注意 **`scan_cacheable` 是顶层配置键**（`DtoConfig` 直接 `$config->get('scan_cacheable')`），而 `proxy_dir`、`dto_default_value_level`、`responses_global_convert` 在 `dto`/`api_docs` 键下。

## 与其他包的软集成

- `ValidationManager` 中 `class_exists(ApiModelProperty::class)` 探测 `tangwei/apidocs` 是否存在 —— 两包可独立使用，但同装时 apidocs 的注解值会并入验证规则
- JSON-RPC 返回对象需消费方手动启用 `ObjectNormalizerAspect` 并绑定 `NormalizerInterface`（见 README「RPC 支持」）

## 测试要点

- `tests/bootstrap.php` 定义 `BASE_PATH`；`DtoConfig::getScanHandler()` 检测 `PHPUNIT_COMPOSER_INSTALL` 常量切换到 `ProcScanHandler`
- 测试用 Mockery mock 容器；`tearDown` 中需 `AnnotationCollector::clear()` 避免注解污染后续用例
- 验证注解测试统一继承 `tests/Annotation/Validation/ValidationAnnotationTestCase`（提供 FakeTranslatorLoader 版的 `ValidatorFactory`）
- 新增验证注解的固定搭配：在 `src/Annotation/Validation/` 建类（继承 `BaseValidation`，设置 `$rule`），并在 `tests/Annotation/Validation/` 加对应测试

## 参考文档

- [CODE_WIKI.md](CODE_WIKI.md) — 深度架构文档（调用链图、数据结构、扩展点）
- [README.md](README.md) — 完整使用文档（90+ 验证注解清单、嵌套/枚举/别名/RPC 示例）