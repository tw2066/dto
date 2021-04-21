<?php

declare(strict_types=1);

namespace Hyperf\DTO\Dependencies;

use Hyperf\DTO\Contracts\RequestBody;
use Hyperf\DTO\Contracts\RequestFormData;
use Hyperf\DTO\Contracts\RequestQuery;
use Hyperf\DTO\ValidationDTO;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\DTO\Mapper;
use Hyperf\Utils\Context;
use Psr\Http\Message\ServerRequestInterface;

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
                    if($this->isMap($obj)){
                        $injections[] = $this->validateAndMap($obj, $definition->getName());
                    }else{
                        $injections[] = $obj;
                    }
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

    private function isMap($obj){
        if(    $obj instanceof RequestBody
            || $obj instanceof RequestQuery
            || $obj instanceof RequestFormData
        ){
            return true;
        }
        return false;
    }

    private function validateAndMap($obj, $className)
    {
        $validationDTO = $this->container->get(ValidationDTO::class);
        $request = Context::get(ServerRequestInterface::class);
        $param = [];
        if ($obj instanceof RequestBody) {
            $param = $request->getParsedBody();
        } else if ($obj instanceof RequestQuery) {
            $param = $request->getQueryParams();
        } else if ($obj instanceof RequestFormData) {
            $param = $request->getParsedBody();
        }
        $validationDTO->validate($className, $param);
        return Mapper::map($param, make($className));
    }
}
