<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class RequiredUnless extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'required_unless';

    /**
     * 当另一个字段不等于某个值时，验证字段必须存在且不为空.
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

