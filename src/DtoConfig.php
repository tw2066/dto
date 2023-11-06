<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\ScanHandler\PcntlScanHandler;
use Hyperf\Di\ScanHandler\ScanHandlerInterface;
use Hyperf\DTO\Type\Convert;

class DtoConfig
{
    private static string $dto_alias_method_prefix = '_set_dto_alias_';

    private string $proxy_dir = '';

    private int $dto_default_value_level = 0;

    private ?Convert $responses_global_convert = null;
    private ?ScanHandlerInterface $scan_handler = null;

    public function __construct(ConfigInterface $config)
    {
        $data = $config->get('dto', []) ?: $config->get('api_docs', []);
        $jsonMapper = Mapper::getJsonMapper('bIgnoreVisibility');
        // 私有属性和函数
        $jsonMapper->bIgnoreVisibility = true;
        $jsonMapper->map($data, $this);
    }

    public function getResponsesGlobalConvert(): ?Convert
    {
        return $this->responses_global_convert;
    }

    public function getProxyDir(): string
    {
        return $this->proxy_dir ?: BASE_PATH . '/runtime/container/proxy/';
    }

    public function setProxyDir(string $proxy_dir): void
    {
        $this->proxy_dir = rtrim($proxy_dir, '/') . '/';
    }

    public function getDtoDefaultValueLevel(): int
    {
        return $this->dto_default_value_level;
    }

    public static function getDtoAliasMethodName(string $fieldName): string
    {
        return static::$dto_alias_method_prefix . $fieldName;
    }

    /**
     * @return ScanHandlerInterface
     */
    public function getScanHandler(): ScanHandlerInterface
    {
        return $this->scan_handler ?? new PcntlScanHandler();
    }

}
