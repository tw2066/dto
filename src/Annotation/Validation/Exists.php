<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;
use Hyperf\Validation\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Exists extends BaseValidation
{
    use DatabaseRule;

    /**
     * 验证字段必须存在于指定数据表.
     * @param string $table 表名, 支持模型 eg: App\Model\User::class
     * @param string $column 数据库字段，不指定 column 选项，字段名将作为默认 column
     * @param array $wheres 简单查询条件 eg: [['status', '=', '1']]
     */
    public function __construct(protected string $table, protected string $column = 'NULL', protected array $wheres = [], string $message = '')
    {
        parent::__construct($message);
        $this->table = $this->resolveTableName($this->table);
    }

    public function getRule(): mixed
    {
        $rule = Rule::exists($this->table, $this->column);
        if ($this->wheres) {
            $rule->where(function ($query) {
                $query->where($this->wheres);
            });
        }

        return $rule;
    }
}
