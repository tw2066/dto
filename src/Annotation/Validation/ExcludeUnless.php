<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ExcludeUnless extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'exclude_unless';

    /**
     * 当另一个字段不等于某个值时，指示验证器忽略该字段.
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

