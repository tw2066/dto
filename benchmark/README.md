# DTO 映射基准

在包目录运行：

```bash
php8.4 -d xdebug.mode=off benchmark/input_mapping.php --iterations=5000 --rounds=8
```

脚本执行 8 轮，丢弃首轮后输出中位数和 p95，覆盖简单 DTO、未知键、setter、header、JSONField、嵌套 DTO、1000 项数组以及 Valid + 映射。每个场景都会检查映射结果，避免把错误实现当成性能提升。

## 与指定基线比较

先将基线源码提取到独立临时目录（不要覆盖当前工作区）：

```bash
baseline_dir=$(mktemp -d /tmp/dto-baseline.XXXXXX)
git archive 23c628f src | tar -x -C "$baseline_dir"
```

`DTO_SOURCE_DIR` 会将该目录放到 `Hyperf\DTO\` 自动加载路径的最前面，依赖仍使用当前 `vendor`：

```bash
DTO_SOURCE_DIR="$baseline_dir/src" php8.4 -d xdebug.mode=off \
  benchmark/input_mapping.php --iterations=5000 --rounds=8
```

基线与当前版本应在同一机器、相同 PHP/依赖和参数下顺序运行。不要并行跑两组基准，也不要用不同脚本或不同输入数据直接比较。

## 与原版 JsonMapper 比较

```bash
php8.4 -d xdebug.mode=off benchmark/upstream_comparison.php --iterations=10000 --rounds=12
```

该脚本直接对比 `Hyperf\DTO\JsonMapper` 与 `netresearch/jsonmapper` 的原版 `\JsonMapper`，统一映射器配置并验证映射结果。每轮交换执行顺序，丢弃首轮后输出中位数、p95 和 `hyperf_speedup`（大于 1 表示 Hyperf 更快）。

场景仅包含两者共同支持的简单字段、未知键、setter、嵌套对象、对象数组和 1000 条批量映射；`JSONField` 和 DTO 验证不属于原版能力，不在此对比中。首次反射、docblock 解析和映射计划构建会在预热阶段完成，不计入稳态耗时。

## P3-3：请求期验证/映射懒加载

`p33_validation_io.php` 量化“每个 worker 每个类首次 `#[Valid]` 请求”的一次性开销，并统计 `file_get_contents` 调用次数。夹具为父 DTO（8 属性，4 个 docblock）+ 嵌套子 DTO（2 属性，1 个 docblock）。

```bash
php8.4 -d xdebug.mode=off benchmark/p33_validation_io.php --iterations=1000 --rounds=8 --samples=21
```

计时场景：

- `lazy_first_validate`：PropertyManager 未预扫描，首次验证包含属性扫描 + 规则生成 + 映射计划构建。
- `prescanned_first_validate`：模拟启动期扫描并生成规则（含 `markGenerated()`），首次验证仍会构建映射计划。
- `prewarmed_first_validate`：在上一场景基础上预构建映射计划，首次验证只保留验证器/翻译属性首次初始化等固定开销，不再重复生成规则。
- `steady_validate` / `steady_map`：预热后的稳态耗时。
- `property_scan_first_call` / `property_scan_second_call`：属性扫描冷/热对比。
- `generate_rules_only`：只执行 `ValidationManager::generateValidation()`，证明规则生成本身不读文件。

`file_get_contents` 调用次数需要 Xdebug function monitor（`xdebug.mode=develop`），每个场景单独进程运行，避免监控数据累积：

```bash
php8.4 -d xdebug.mode=develop benchmark/p33_validation_io.php --monitor=lazy
php8.4 -d xdebug.mode=develop benchmark/p33_validation_io.php --monitor=simple
php8.4 -d xdebug.mode=develop benchmark/p33_validation_io.php --monitor=prescanned
php8.4 -d xdebug.mode=develop benchmark/p33_validation_io.php --monitor=prewarmed
php8.4 -d xdebug.mode=develop benchmark/p33_validation_io.php --monitor=steady
```

预期调用次数：lazy = 4（父/子类各由 `DtoCommon` 与 `JsonMapper` 读一次），simple = 0（纯标量 DTO 没有 docblock），prescanned = 2（只剩 `JsonMapper` 映射计划），prewarmed/steady = 0。所有读取都应来自 `src/JsonMapper.php` 的 `getDocBlockContext()`；若出现其他调用点，说明新增了未预期的请求期 IO。

