<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Hyperf\DTO\JsonMapper as DtoJsonMapper;

require dirname(__DIR__) . '/vendor/autoload.php';

$options = getopt('', ['iterations::', 'rounds::']);
$iterations = max(1, (int) ($options['iterations'] ?? 3000));
$rounds = max(2, (int) ($options['rounds'] ?? 8));

$simpleInput = ['name' => 'alice', 'age' => 30];
$unknownInput = ['name' => 'alice', 'age' => 30, 'unknown' => 'ignored'];
$setterInput = ['name' => 'setter'];
$nestedInput = [
    'child' => ['name' => 'child', 'age' => 3],
    'note' => 'nested',
];
$objectArrayInput = [
    'items' => [
        ['name' => 'first', 'age' => 1],
        ['name' => 'second', 'age' => 2],
    ],
];
$batchInput = [];
for ($i = 0; $i < 1000; ++$i) {
    $batchInput[] = ['name' => 'user' . $i, 'age' => $i];
}

$cases = [
    'simple_map' => [
        static fn (JsonMapper $mapper) => $mapper->map($simpleInput, new UpstreamBenchmarkSimpleDto()),
        static fn ($dto) => $dto->name === 'alice' && $dto->age === 30,
        $iterations,
    ],
    'unknown_key_map' => [
        static fn (JsonMapper $mapper) => $mapper->map($unknownInput, new UpstreamBenchmarkSimpleDto()),
        static fn ($dto) => $dto->name === 'alice' && $dto->age === 30,
        $iterations,
    ],
    'setter_map' => [
        static fn (JsonMapper $mapper) => $mapper->map($setterInput, new UpstreamBenchmarkSetterDto()),
        static fn ($dto) => $dto->getName() === 'setter',
        $iterations,
    ],
    'nested_object_map' => [
        static fn (JsonMapper $mapper) => $mapper->map($nestedInput, new UpstreamBenchmarkNestedDto()),
        static fn ($dto) => $dto->child?->name === 'child'
            && $dto->child->age === 3
            && $dto->note === 'nested',
        $iterations,
    ],
    'object_array_map' => [
        static fn (JsonMapper $mapper) => $mapper->map($objectArrayInput, new UpstreamBenchmarkObjectArrayDto()),
        static fn ($dto) => count($dto->items) === 2
            && $dto->items[0]->name === 'first'
            && $dto->items[1]->age === 2,
        $iterations,
    ],
    'batch_1000_map' => [
        static fn (JsonMapper $mapper) => $mapper->mapArray($batchInput, [], UpstreamBenchmarkSimpleDto::class),
        static fn ($items) => count($items) === 1000 && $items[999]->age === 999,
        max(1, intdiv($iterations, 100)),
    ],
];

$mapperFactories = [
    'upstream' => static fn () => new JsonMapper(),
    'hyperf_dto' => static fn () => new DtoJsonMapper(),
];
$mappers = [];
foreach ($mapperFactories as $name => $factory) {
    $mapper = $factory();
    // 与 Hyperf\DTO\Mapper 的默认运行时配置保持一致。
    $mapper->bEnforceMapType = false;
    $mapper->bStrictNullTypes = false;
    $mapper->bStrictNullTypesInArrays = false;
    $mappers[$name] = $mapper;

    // 首次反射、docblock 解析和映射计划构建不计入稳态耗时。
    foreach ($cases as $caseName => [$run, $verify]) {
        $run($mapper);
        if (! $verify($run($mapper))) {
            throw new RuntimeException("Benchmark result verification failed: {$name}/{$caseName}");
        }
    }
}

$samples = [];
$mapperNames = array_keys($mappers);
foreach ($cases as $caseName => [$run, $verify, $caseIterations]) {
    for ($round = 0; $round < $rounds; ++$round) {
        // 每轮交换执行顺序，降低固定先后顺序造成的偏差。
        $order = $round % 2 === 0 ? $mapperNames : array_reverse($mapperNames);
        foreach ($order as $mapperName) {
            $start = hrtime(true);
            for ($i = 0; $i < $caseIterations; ++$i) {
                $run($mappers[$mapperName]);
            }
            $samples[$caseName][$mapperName][] = (hrtime(true) - $start) / $caseIterations;
        }
    }
}

$results = [];
foreach ($samples as $caseName => $mapperSamples) {
    $metrics = [];
    foreach ($mapperSamples as $mapperName => $values) {
        array_shift($values);
        sort($values);
        $median = (int) $values[intdiv(count($values), 2)];
        $metrics[$mapperName] = [
            'median_ns_per_operation' => $median,
            'p95_round_ns_per_operation' => (int) $values[(int) ceil(count($values) * 0.95) - 1],
            'operations_per_second' => (int) round(1_000_000_000 / $median),
            'samples_ns' => array_map(static fn ($value) => (int) $value, $values),
        ];
    }
    $results[$caseName] = [
        'iterations' => $cases[$caseName][2],
        'mappers' => $metrics,
        'hyperf_speedup' => round(
            $metrics['upstream']['median_ns_per_operation'] / $metrics['hyperf_dto']['median_ns_per_operation'],
            3
        ),
    ];
}

echo json_encode([
    'php' => PHP_VERSION,
    'upstream_version' => InstalledVersions::getPrettyVersion('netresearch/jsonmapper'),
    'xdebug_mode' => ini_get('xdebug.mode'),
    'opcache_cli' => (bool) ini_get('opcache.enable_cli'),
    'rounds' => $rounds - 1,
    'peak_memory_bytes' => memory_get_peak_usage(true),
    'cases' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

class UpstreamBenchmarkSimpleDto
{
    public string $name = '';

    public int $age = 0;
}

class UpstreamBenchmarkSetterDto
{
    private string $name = '';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}

class UpstreamBenchmarkNestedDto
{
    public ?UpstreamBenchmarkSimpleDto $child = null;

    public string $note = '';
}

class UpstreamBenchmarkObjectArrayDto
{
    /** @var UpstreamBenchmarkSimpleDto[] */
    public array $items = [];
}
