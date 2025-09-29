<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;
use Hyperf\Database\Query\Builder;
use Hyperf\Validation\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Exists extends BaseValidation
{
    /**
     * 验证字段必须存在于指定数据表
     */
    public function __construct(string $table, string $column = 'NULL',array $wheres = [], public string $messages = '')
    {
        $rule = Rule::exists($table, $column);

        if ($wheres){
            foreach ($wheres as $column => $where){
                $rule->where($column, $where);
            }
        }
        $this->rule = $rule;
        parent::__construct($messages);
    }
}
