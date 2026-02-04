<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Doctrine\Instantiator\Exception\ExceptionInterface;
use Doctrine\Instantiator\Instantiator;
use Hyperf\Database\Model\Model;

trait DatabaseRule
{
    /**
     * Resolves the name of the table from the given string.
     */
    public function resolveTableName(string $table)
    {
        if (! str_contains($table, '\\') || ! class_exists($table)) {
            return $table;
        }

        if (is_subclass_of($table, Model::class)) {
            $instantiator = new Instantiator();
            /** @var Model $model */
            $model = $instantiator->instantiate($table);

            if (str_contains($model->getTable(), '.')) {
                return $table;
            }

            return implode('.', array_map(function (string $part) {
                return trim($part, '.');
            }, array_filter([$model->getConnectionName(), $model->getTable()])));
        }

        return $table;
    }
}
