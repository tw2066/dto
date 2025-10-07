<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Declined extends BaseValidation
{
    protected mixed $rule = 'declined';

    /**
     * 正在验证的字段必须是 no、off、0 或者 false.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}
