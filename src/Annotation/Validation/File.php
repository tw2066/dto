<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class File extends BaseValidation
{
    protected mixed $rule = 'file';

    /**
     * 验证字段必须是成功上传的文件.
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}

