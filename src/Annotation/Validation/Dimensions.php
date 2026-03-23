<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Dimensions extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'dimensions';

    /**
     * 验证图片必须满足指定尺寸规则，例如：min_width=100,min_height=200,max_width=1000,max_height=2000,ratio=3/2.
     * @param array $dimensions 规则列表
     */
    public function __construct(array $dimensions, string $message = '')
    {
        $this->rule .= ':' . implode(',', $dimensions);
        parent::__construct($message);
    }
}

