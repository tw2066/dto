<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Hyperf\DTO\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;

abstract class BaseValidation extends AbstractAnnotation
{
    /**
     * @var string
     */
    public $rule;

    /**
     * @var string
     */
    public $messages = '';

}
