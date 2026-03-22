<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class RequiredWithoutAll extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'required_without_all';

    /**
     * 当所有给定字段为空或缺失时，验证字段必须存在且不为空.
     * @param array $anotherFields 字段列表
     */
    public function __construct(array $anotherFields, string $message = '')
    {
        $this->rule .= ':' . implode(',', $anotherFields);
        parent::__construct($message);
    }
}

