<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Contract\ConfigInterface;
use Hyperf\DTO\Type\Convert;

class DtoConfig
{
    private string $proxy_dir;
    private bool $is_set_default_value = false;

    private ?Convert $responses_global_convert = null;

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

    /**
     * @return bool
     */
    public function isIsSetDefaultValue(): bool
    {
        return $this->is_set_default_value;
    }


}
