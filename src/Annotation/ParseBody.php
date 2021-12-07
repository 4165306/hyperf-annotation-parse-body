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
namespace Caterpillar\HyperfAnnotationParseBody\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @Target("ALL")
 * @Annotation
 */
#[\Attribute(\Attribute::TARGET_ALL)]
class ParseBody extends AbstractAnnotation
{
    public function __construct(...$value)
    {
        parent::__construct($value);
    }
}
