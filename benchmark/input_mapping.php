<?php

declare(strict_types=1);

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DTO\Annotation\JSONField;
use Hyperf\DTO\Annotation\Validation\Max;
use Hyperf\DTO\Annotation\Validation\Required;
use Hyperf\DTO\DtoCommon;
use Hyperf\DTO\DtoValidation;
use Hyperf\DTO\Mapper;
use Hyperf\DTO\Scan\PropertyEnum;
use Hyperf\DTO\Scan\PropertyManager;
use Hyperf\DTO\Scan\ValidationManager;
use Hyperf\Translation\Translator;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\ValidatorFactory;
use HyperfTest\DTO\Annotation\Validation\FakeTranslatorLoader;
use Psr\Container\ContainerInterface;

$loader = require dirname(__DIR__) . '/vendor/autoload.php';
$sourceDir = getenv('DTO_SOURCE_DIR') ?: null;
if ($sourceDir !== null) {
    // Composer 默认会把自己 prepend 到 autoload 链首，addPsr4 无法覆盖 classmap；
    // 必须在 require vendor/autoload.php 之后 prepend 独立 loader 才能加载基线源码。
    spl_autoload_register(static function (string $class) use ($sourceDir): void {
        $prefix = 'Hyperf\\DTO\\';
        if (! str_starts_with($class, $prefix)) {
            return;
        }
        $file = $sourceDir . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }, true, true);
}

$options = getopt('', ['iterations::', 'rounds::']);
$iterations = max(1, (int) ($options['iterations'] ?? 3000));
$rounds = max(2, (int) ($options['rounds'] ?? 8));

$translator = new Translator(new FakeTranslatorLoader(), 'en');
$validatorFactory = new ValidatorFactory($translator);
$container = new class($validatorFactory) implements ContainerInterface {
    public function __construct(private ValidatorFactoryInterface $validatorFactory) {}

    public function get(string $id)
    {
        return $id === ValidatorFactoryInterface::class ? $this->validatorFactory : null;
    }

    public function has(string $id): bool
    {
        return $id === ValidatorFactoryInterface::class || $id === StdoutLoggerInterface::class;
    }
};
ApplicationContext::setContainer($container);

(new JSONField('user_name'))->collectProperty(BenchmarkAliasDto::class, 'userName');
(new Required())->collectProperty(BenchmarkAliasDto::class, 'userName');
(new Max(50))->collectProperty(BenchmarkAliasDto::class, 'userName');
$validationManager = new ValidationManager();
$validationManager->generateValidation(BenchmarkAliasDto::class, 'userName');
$validation = new DtoValidation(new PropertyManager(new DtoCommon(), new PropertyEnum()), $validationManager);

$simpleInput = ['name' => 'alice', 'age' => 30];
$unknownInput = ['name' => 'alice', 'age' => 30, 'unknown' => 'ignored'];
$aliasInput = ['user_name' => 'alice'];
$setterInput = ['user_name' => 'setter'];
$headerInput = ['x-request-id' => 'request-id'];
$nestedInput = [
    'child' => ['user_name' => 'child'],
    'items' => [['user_name' => 'first'], ['user_name' => 'second']],
];
$batchInput = [];
for ($i = 0; $i < 1000; ++$i) {
    $batchInput[] = ['name' => 'user' . $i, 'age' => $i];
}

$cases = [
    'simple_map' => [
        static fn () => Mapper::map($simpleInput, new BenchmarkSimpleDto()),
        static fn ($dto) => $dto->name === 'alice' && $dto->age === 30,
        $iterations,
    ],
    'unknown_key_map' => [
        static fn () => Mapper::map($unknownInput, new BenchmarkSimpleDto()),
        static fn ($dto) => $dto->name === 'alice' && $dto->age === 30,
        $iterations,
    ],
    'setter_map' => [
        static fn () => Mapper::map($setterInput, new BenchmarkSetterDto()),
        static fn ($dto) => $dto->getUserName() === 'setter',
        $iterations,
    ],
    'hyphen_header_map' => [
        static fn () => Mapper::map($headerInput, new BenchmarkHeaderDto()),
        static fn ($dto) => $dto->xRequestId === 'request-id',
        $iterations,
    ],
    'alias_map' => [
        static fn () => Mapper::map($aliasInput, new BenchmarkAliasDto()),
        static fn ($dto) => $dto->userName === 'alice',
        $iterations,
    ],
    'nested_alias_map' => [
        static fn () => Mapper::map($nestedInput, new BenchmarkNestedDto()),
        static fn ($dto) => $dto->child?->userName === 'child'
            && $dto->items[0]->userName === 'first'
            && $dto->items[1]->userName === 'second',
        $iterations,
    ],
    'batch_1000_map' => [
        static fn () => Mapper::mapArray($batchInput, BenchmarkSimpleDto::class),
        static fn ($items) => count($items) === 1000 && $items[999]->age === 999,
        max(1, intdiv($iterations, 100)),
    ],
    'alias_validate_map' => [
        static function () use ($validation, $aliasInput) {
            if (method_exists($validation, 'prepare')) {
                $resolved = $validation->prepare(BenchmarkAliasDto::class, $aliasInput);
                $validation->validateResolvedInput($resolved);
                return Mapper::mapResolvedInput($resolved, new BenchmarkAliasDto());
            }
            $validation->validate(BenchmarkAliasDto::class, $aliasInput);
            return Mapper::map($aliasInput, new BenchmarkAliasDto());
        },
        static fn ($dto) => $dto->userName === 'alice',
        $iterations,
    ],
];

$results = [];
foreach ($cases as $name => [$run, $verify, $caseIterations]) {
    $run();
    if (! $verify($run())) {
        throw new RuntimeException("Benchmark result verification failed: {$name}");
    }

    $samples = [];
    for ($round = 0; $round < $rounds; ++$round) {
        $start = hrtime(true);
        for ($i = 0; $i < $caseIterations; ++$i) {
            $run();
        }
        $samples[] = (hrtime(true) - $start) / $caseIterations;
    }
    array_shift($samples);
    sort($samples);
    $results[$name] = [
        'iterations' => $caseIterations,
        'median_ns_per_operation' => (int) $samples[intdiv(count($samples), 2)],
        'p95_ns_per_operation' => (int) $samples[(int) ceil(count($samples) * 0.95) - 1],
        'samples_ns' => array_map(static fn ($value) => (int) $value, $samples),
    ];
}

echo json_encode([
    'php' => PHP_VERSION,
    'source_dir' => $sourceDir,
    'rounds' => $rounds - 1,
    'memory_bytes' => memory_get_usage(true),
    'peak_memory_bytes' => memory_get_peak_usage(true),
    'cases' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

class BenchmarkSimpleDto
{
    public string $name = '';

    public int $age = 0;
}

class BenchmarkAliasDto
{
    public string $userName = '';

    // 3.x 基线的代理等价物；4.0 会忽略该方法并直接映射公开属性。
    private function _set_dto_alias_c56f5648d4c0e137f9de3dfc7a54d55b(string $param): void
    {
        $this->userName = $param;
    }
}

class BenchmarkNestedDto
{
    public ?BenchmarkAliasDto $child = null;

    /** @var BenchmarkAliasDto[] */
    public array $items = [];
}

class BenchmarkSetterDto
{
    private string $userName = '';

    public function setUserName(string $userName): void
    {
        $this->userName = $userName;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }
}

class BenchmarkHeaderDto
{
    public string $xRequestId = '';
}
