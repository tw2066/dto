<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class RequiredWithAll extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'required_with_all';

    /**
     * 当所有给定字段存在且不为空时，验证字段必须存在且不为空.
     * @param array $anotherFields 字段列表
     */
    public function __construct(array $anotherFields, string $message = '')
    {
        $this->rule .= ':' . implode(',', $anotherFields);
        parent::__construct($message);
    }
}

