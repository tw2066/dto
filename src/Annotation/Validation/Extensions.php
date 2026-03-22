<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Extensions extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'extensions';

    /**
     * 验证文件扩展名必须在给定列表中.
     * @param array $extensions 扩展名列表，例如：['jpg', 'png']
     */
    public function __construct(array $extensions, string $message = '')
    {
        $this->rule .= ':' . implode(',', $extensions);
        parent::__construct($message);
    }
}

