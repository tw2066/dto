<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Tang\DTO\Dependencies;

use Tang\DTO\Contracts\RequestBody;
use Tang\DTO\Contracts\RequestFormData;
use Tang\DTO\Contracts\RequestQuery;
use Hyperf\HttpServer\Contract\RequestInterface;
use Tang\DTO\Mapper;


class CoreMiddleware extends \Hyperf\HttpServer\CoreMiddleware
{

    protected function parseMethodParameters(string $controller, string $action, array $arguments): array
    {
        $definitions = $this->getMethodDefinitionCollector()->getParameters($controller, $action);
        return $this->getInjections($definitions, "{$controller}::{$action}", $arguments);
    }

    private function getInjections(array $definitions, string $callableName, array $arguments): array
    {
        $injections = [];
        foreach ($definitions ?? [] as $pos => $definition) {
            $value = $arguments[$pos] ?? $arguments[$definition->getMeta('name')] ?? null;
            if ($value === null) {
                if ($definition->getMeta('defaultValueAvailable')) {
                    $injections[] = $definition->getMeta('defaultValue');
                } elseif ($definition->allowsNull()) {
                    $injections[] = null;
                } elseif ($this->container->has($definition->getName())) {
                    $obj = $this->container->get($definition->getName());
                    if($obj instanceof RequestBody){
                        $request = $this->container->get(RequestInterface::class);
                        $json = $request->getBody()->getContents();
                        $mapper = $this->container->get(Mapper::class);
                        $class = $definition->getName();
                        $injections[] = $mapper->map(json_decode($json), make($class));
                        continue;
                    }elseif ($obj instanceof RequestQuery) {
                        $request = $this->container->get(RequestInterface::class);
                        $mapper = $this->container->get(Mapper::class);
                        $injections[] = $mapper->map($request->getQueryParams(), $obj);
                        continue;
                    }elseif ($obj instanceof RequestFormData) {
                        $request = $this->container->get(RequestInterface::class);
                        $mapper = $this->container->get(Mapper::class);
                        $injections[] = $mapper->map($request->getParsedBody(), $obj);
                        continue;
                    }
                    $injections[] = $obj;
                } else {
                    throw new \InvalidArgumentException("Parameter '{$definition->getMeta('name')}' "
                        . "of {$callableName} should not be null");
                }
            } else {
                $injections[] = $this->getNormalizer()->denormalize($value, $definition->getName());
            }
        }
        return $injections;
    }
}
