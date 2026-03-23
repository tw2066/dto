<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Mimes extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'mimes';

    /**
     * 验证文件的 MIME 类型必须对应给定扩展名列表.
     * @param array $extensions 扩展名列表，例如：['jpg', 'png']
     */
    public function __construct(array $extensions, string $message = '')
    {
        $this->rule .= ':' . implode(',', $extensions);
        parent::__construct($message);
    }
}

