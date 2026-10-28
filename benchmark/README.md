# DTO 映射基准

在包目录运行：

```bash
php8.4 -d xdebug.mode=off benchmark/input_mapping.php --iterations=5000 --rounds=8
```

脚本执行 8 轮，丢弃首轮后输出中位数和 p95，覆盖简单 DTO、未知键、setter、连字符 header、JSONField、嵌套 DTO、1000 项数组以及 Valid + 映射。每个场景都会检查映射结果，避免把错误实现当成性能提升。

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

基线与当前版本应在同一机器、相同 PHP/依赖和参数下顺序运行。不要并行跑两组基准，也不要用不同脚本或不同输入数据直接比较。`method_exists($validation, 'prepare')` 分支仅用于让同一脚本兼容 3.x 与 4.0 的 Valid 调用方式。
