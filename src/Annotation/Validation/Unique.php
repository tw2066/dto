<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;
use Hyperf\Validation\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Unique extends BaseValidation
{
    protected ?string $ignoreIdColumn = null;

    /**
     * 验证字段在给定的数据库表中不得存在.
     */
    public function __construct(string $table, string $column = 'NULL', ?string $ignoreIdColumn = null, array $wheres = [], public string $messages = '')
    {
        $rule = Rule::unique($table, $column);
        // $rule->ignore($user->id);
        //
        // $rule->where(function ($query) use ($ignoreIdColumn) {
        //
        // });
        if ($wheres) {
            foreach ($wheres as $column => $where) {
                $rule->where($column, $where);
            }
        }
        $this->rule = $rule;
        $this->ignoreIdColumn = $ignoreIdColumn;
        parent::__construct($messages);
    }
}
