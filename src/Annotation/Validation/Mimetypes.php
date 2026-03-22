<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Mimetypes extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'mimetypes';

    /**
     * 验证文件的 MIME 类型必须在给定列表中.
     * @param array $mimetypes MIME 类型列表，例如：['image/jpeg', 'image/png']
     */
    public function __construct(array $mimetypes, string $message = '')
    {
        $this->rule .= ':' . implode(',', $mimetypes);
        parent::__construct($message);
    }
}

