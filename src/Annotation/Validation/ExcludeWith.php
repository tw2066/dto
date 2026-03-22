<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ExcludeWith extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'exclude_with';

    /**
     * 当另一个字段存在时，指示验证器忽略该字段.
     */
    public function __construct(string $anotherField, string $message = '')
    {
        $this->rule .= ':' . $anotherField;
        parent::__construct($message);
    }
}

