<?php

declare(strict_types=1);

namespace Hyperf\DTO;

use Hyperf\Di\Aop\AstVisitorRegistry;
use Hyperf\DTO\Visitor\GetterSetterHandlerVisitor;

class Data
{

    public static function insertVisitor()
    {
        AstVisitorRegistry::insert(GetterSetterHandlerVisitor::class, PHP_INT_MAX / 2);
    }
}
