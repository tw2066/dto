<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Bail extends BaseValidation
{
    protected mixed $rule = 'bail';

    /**
     * 第一个验证规则验证失败则停止运行其它验证规则.
     */
    public function __construct(string $messages = '')
    {
        parent::__construct($messages);
    }
}
