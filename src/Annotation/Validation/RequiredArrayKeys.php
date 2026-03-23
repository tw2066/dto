<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class RequiredArrayKeys extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'required_array_keys';

    /**
     * 验证字段必须是数组，且包含给定键名.
     * @param array $keys 键名列表
     */
    public function __construct(array $keys, string $message = '')
    {
        $this->rule .= ':' . implode(',', $keys);
        parent::__construct($message);
    }
}

