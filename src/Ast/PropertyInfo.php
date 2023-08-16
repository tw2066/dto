<?php

declare(strict_types=1);

namespace Hyperf\DTO\Ast;

class PropertyInfo
{
    public string $propertyName = '';

    public string $getMethodName = '';

    public string $alias = '';

    public string $arrKey = '';
}
