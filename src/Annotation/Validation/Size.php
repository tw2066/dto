<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Size extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'size';

    /**
     * 验证字段必须有和给定值 value 相匹配的尺寸/大小，对字符串而言，value 是相应的字符数目；对数值而言，value 是给定整型值；对数组而言，value 是数组长度；对文件而言，value 是相应的文件千字节数（KB）.
     */
    public function __construct(int $value, string $message = '')
    {
        parent::__construct($message);
        $this->rule .= ':' . $value;
    }
}
