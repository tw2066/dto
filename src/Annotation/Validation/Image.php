<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Image extends BaseValidation
{
    protected mixed $rule = 'image';

    /**
     * 验证文件必须是图片（jpeg、png、bmp、gif 或者 svg）.
     */
    public function __construct(string $messages = '')
    {
        parent::__construct($messages);
    }
}
