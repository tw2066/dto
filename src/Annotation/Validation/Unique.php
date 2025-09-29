<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;
use Hyperf\Context\ApplicationContext;
use Hyperf\Database\Query\Builder;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Unique extends BaseValidation
{
    /**
     * 验证字段在给定的数据库表中不得存在.
     */
    public function __construct(protected string $table, protected string $column = 'NULL', protected ?string $ignoreIdKey = null, protected ?string $ignoreIdColumn = null, protected array $wheres = [], public string $messages = '')
    {
        parent::__construct($messages);
    }

    public function getRule(): mixed
    {
        $rule = Rule::unique($this->table, $this->column);

        if ($this->ignoreIdKey) {
            $rule->where(function (Builder $query) {
                $request = ApplicationContext::getContainer()->get(RequestInterface::class);
                $excludeId = $request->input($this->ignoreIdKey);
                $query->where($this->ignoreIdColumn ?: 'id', '<>', $excludeId);
            });
        }

        if ($this->wheres) {
            foreach ($this->wheres as $column => $where) {
                $rule->where($column, $where);
            }
        }
        $this->rule = $rule;
        return $this->rule;
    }
}
