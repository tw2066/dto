<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MissingWithAll extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'missing_with_all';

    /**
     * 当所有给定字段都存在时，验证字段在输入数据中必须缺失.
     * @param array $anotherFields 字段列表
     */
    public function __construct(array $anotherFields, string $message = '')
    {
        $this->rule .= ':' . implode(',', $anotherFields);
        parent::__construct($message);
    }
}

