<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\ScanHandler\PcntlScanHandler;
use Hyperf\Di\ScanHandler\ProcScanHandler;
use Hyperf\Di\ScanHandler\ScanHandlerInterface;
use Hyperf\DTO\Type\Convert;

/**
 * DTO configuration manager.
 */
class DtoConfig
{
    private static string $dto_alias_method_prefix = '_set_dto_alias_';

    private string $proxy_dir = '';

    private int $dto_default_value_level = 0;

    private bool $scan_cacheable;

    private ?Convert $responses_global_convert = null;

    private ?ScanHandlerInterface $scan_handler = null;

    public function __construct(ConfigInterface $config)
    {
        $this->scan_cacheable = $config->get('scan_cacheable', false);
        $data = $config->get('dto', []) ?: $config->get('api_docs', []);
        $jsonMapper = Mapper::getJsonMapper('bIgnoreVisibility');
        // Enable private property and method access
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
        // See JsonMapper::getCamelCaseName
        $fieldName = str_replace(
            ' ',
            '',
            ucwords(str_replace(['_', '-'], ' ', $fieldName))
        );
        $fieldName = md5($fieldName);
        return static::$dto_alias_method_prefix . $fieldName;
    }

    public function getScanHandler(): ScanHandlerInterface
    {
        if ($this->scan_handler) {
            return $this->scan_handler;
        }
        if (defined('PHPUNIT_COMPOSER_INSTALL')) {
            return new ProcScanHandler();
        }
        return new PcntlScanHandler();
    }

    public function isScanCacheable(): bool
    {
        return $this->scan_cacheable;
    }
}
