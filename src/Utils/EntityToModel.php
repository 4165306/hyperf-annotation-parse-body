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
namespace Caterpillar\HyperfAnnotationParseBody\Utils;

use App\Model\Model;
use Hyperf\Di\ReflectionManager;
use Swoole\ArrayObject;

class EntityToModel
{
    /**
     * exp $model = (new EntityModel())->toModel($entity, $model, swoole_array(['excludeFields1', 'excludeFields2'])).
     * @param null|ArrayObject $exclude 排除实体类的字段
     */
    public function toModel(object $entity, Model $model, ?ArrayObject $exclude = null): Model
    {
        if ($exclude === null) {
            $exclude = swoole_array([]);
        }
        $className = get_class($entity);
        $refClass = ReflectionManager::reflectClass($className);
        $reflectionProperties = $refClass->getProperties();
        foreach ($reflectionProperties as $reflectionProperty) {
            if ($exclude->indexOf($reflectionProperty->getName()) !== false) {
                continue;
            }
            $methodName = 'get' . ucfirst($this->caseUnderlineToTurn($reflectionProperty->getName()));
            try {
                $method = $refClass->getMethod($methodName);
                $value = $method->invoke($entity);
            } catch (\ReflectionException $e) {
                continue;
            }
            $model->setAttribute($reflectionProperty->getName(), $value);
        }
        return $model;
    }

    private function caseUnderlineToTurn(string $str): string
    {
        $result = '_' . str_replace('_', ' ', strtolower($str));
        return ltrim(str_replace(' ', '', ucwords($result)), '_');
    }
}
