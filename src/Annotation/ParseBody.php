<?php

namespace Caterpillar\HyperfAnnotationParseBody\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @author Caterpillar
 * Class ParseBody
 * @package Caterpillar\HyperfAnnotationParseBody\Annotation
 * @Target("ALL")
 * @Annotation
 */
#[\Attribute(\Attribute::TARGET_ALL)]
class ParseBody extends AbstractAnnotation {}