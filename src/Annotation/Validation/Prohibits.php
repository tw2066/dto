<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Prohibits extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'prohibits';

    /**
     * 当验证字段存在且不为空时，给定字段必须不存在或为空.
     * @param array $fields 字段列表
     */
    public function __construct(array $fields, string $message = '')
    {
        $this->rule .= ':' . implode(',', $fields);
        parent::__construct($message);
    }
}

