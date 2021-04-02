<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Hyperf\DTO\Annotation\Validation;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
class Between extends BaseValidation
{
    /**
     * @var string
     */
    public $rule = 'between';

    /**
     * @var int
     */
    public $min;

    /**
     * @var int
     */
    public $max;

    public function __construct($value = null)
    {
        parent::__construct($value);
        $this->rule = $this->rule .':'.$this->min . ',' . $this->max;
    }
}
