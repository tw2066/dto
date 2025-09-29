<?php

declare(strict_types=1);

namespace Hyperf\DTO\Annotation\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Validation extends BaseValidation
{
    /**
     * ### 自定义验证器.
     *
     * 1. 使用框架的`required|date|after:start_date`写法
     *
     * ```
     * //可以通过Validation实现
     * #[Validation('required|numeric','The :attribute field is required|The :attribute field is numeric')]
     * ```
     *
     * 2. 需要支持数组里面是int数据情况 `'intArr.*' => 'integer'`的情况
     *
     * ```
     * //可以通过Validation中customKey来自定义key实现
     * #[Validation('integer', customKey: 'intArr.*')]
     * public array $intArr;
     * ```
     */
    public function __construct(mixed $rule, string $messages = '', string $customKey = '')
    {
        $this->rule = $rule;
        $this->customKey = $customKey;
        parent::__construct($messages);
    }
}
