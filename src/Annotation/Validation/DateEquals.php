<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DateEquals extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'date_equals';

    /**
     * 验证字段必须等于给定日期，日期将会通过 PHP 函数 strtotime 传递.
     */
    public function __construct(string $date, string $message = '')
    {
        $this->rule .= ':' . $date;
        parent::__construct($message);
    }
}

