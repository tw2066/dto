<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Unique extends BaseValidation
{
    /**
     * 验证字段在给定数据表上必须是唯一的.
     * @param string $table 表名
     * @param string $column 数据库字段，不指定 column 选项，字段名将作为默认 column
     * @param null|string $ignoreIdKey 从请求中获取指定的key的值, 唯一检查时忽略给定 ID
     * @param null|string $ignoreIdColumn 如果你的数据表使用主键字段不是 id，可以指定字段名称
     * @param array $wheres 简单查询条件 eg: [['status', '=', '1']]
     */
    public function __construct(
        protected string $table,
        protected string $column = 'NULL',
        protected ?string $ignoreIdKey = null,
        protected ?string $ignoreIdColumn = null,
        protected array $wheres = [],
        string $messages = ''
    ) {
        parent::__construct($messages);
    }

    public function getRule(): mixed
    {
        $rule = Rule::unique($this->table, $this->column);

        if ($this->ignoreIdKey) {
            $rule->where(function ($query) {
                $request = ApplicationContext::getContainer()->get(RequestInterface::class);
                $excludeId = $request->input($this->ignoreIdKey);
                if (! is_null($excludeId) && $excludeId !== 'NULL') {
                    $query->where($this->ignoreIdColumn ?: 'id', '<>', $excludeId);
                }
            });
        }

        if ($this->wheres) {
            $rule->where(function ($query) {
                $query->where($this->wheres);
            });
        }
        return $rule;
    }
}
