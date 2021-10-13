<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

/**
 * 验证字段必须以某个给定值开头.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class StartsWith extends BaseValidation
{
    /**
     * @var string
     */
    protected mixed $rule = 'starts_with';

    /**
     * Max constructor.
     */
    public function __construct(string $value, string $messages = '')
    {
        $this->messages = $messages;
        $this->rule = $this->rule . ':' . $value;
    }
}
