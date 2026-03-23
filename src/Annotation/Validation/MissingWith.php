<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MissingWith extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'missing_with';

    /**
     * 当任一给定字段存在时，验证字段在输入数据中必须缺失.
     * @param array $anotherFields 字段列表
     */
    public function __construct(array $anotherFields, string $message = '')
    {
        $this->rule .= ':' . implode(',', $anotherFields);
        parent::__construct($message);
    }
}

