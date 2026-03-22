<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MissingIf extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'missing_if';

    /**
     * 当另一个字段等于某个值时，验证字段在输入数据中必须缺失.
     */
    public function __construct(string $anotherField, mixed $value, string $message = '')
    {
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        $this->rule .= ':' . $anotherField . ',' . $value;
        parent::__construct($message);
    }
}

