<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

/**
 * 验证字段必须是给定日期之后的一个值，日期将会通过 PHP 函数 strtotime 传递.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class After extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'after';

    /**
     * 验证字段必须是给定日期之后的一个值，日期将会通过 PHP 函数 strtotime 传递.
     */
    public function __construct(string $date, string $message = '')
    {
        parent::__construct($message);
        $this->rule .= ':' . $date;
    }
}
