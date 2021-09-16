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
namespace Caterpillar\HyperfAnnotationParseBody;

use Caterpillar\HyperfAnnotationParseBody\Annotation\ParseBody;
use Caterpillar\HyperfAnnotationParseBody\Exceptions\VariableTypeNotObtained;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Exception\Exception;
use Hyperf\Di\ReflectionManager;
use Hyperf\HttpServer\Contract\RequestInterface;

/**
 * @Aspect
 */
#[Aspect]
class HandleRequestToEntity extends AbstractAspect
{
    // 要切入的注解
    public $annotations = [
        ParseBody::class,
    ];

    /**
     * @Inject
     */
    #[Inject]
    protected RequestInterface $request;

    private array $mapData = [];

    /**
     * @throws Exception
     */
    public function process(?ProceedingJoinPoint $proceedingJoinPoint)
    {
        $controllerMethod = $proceedingJoinPoint->getReflectMethod();
        $mapData = $this->request->all();
        // 获取控制器方法参数列表
        $args = $controllerMethod->getParameters();
        foreach ($args as $arg) {
            // 循环方法参数数据
            $variableTypeName = $this->getVariableTypeName($arg->getType());
            try {
                if ($variableTypeName === null) {
                    throw new VariableTypeNotObtained('unknown variable type');
                }
                $class = ReflectionManager::reflectClass($variableTypeName);
                if ($class->isInterface()) {
                    continue;
                }
                // 对方法参数进行实例化
            } catch (VariableTypeNotObtained|\ReflectionException|\InvalidArgumentException $e) {
                // 反射失败 尝试注入基础类型参数
                $value = $mapData[$arg->getName()];
                $type = $this->getVariableTypeName($arg->getType());
                if ($type && $value) {
                    settype($value, $type == 'int' ? 'integer' : $type);
                }
                $params[$arg->getName()] = $value;
                continue;
            }
            // 实体类
            try {
                $newClass = $this->setEntityClass($variableTypeName, $mapData);
            }catch (VariableTypeNotObtained $e) {
                continue;
            }
            var_dump('newClass', $newClass);
            $proceedingJoinPoint->arguments['keys'][$arg->getName()] = $newClass;
        }
        return $proceedingJoinPoint->process();
    }

    private function setEntityClass(string $className, array $dataSource)
    {
        try {
            $classRef = ReflectionManager::reflectClass($className);
        }catch (\InvalidArgumentException $e) {
            throw new VariableTypeNotObtained('类不存在');
        }
        // 类私有属性
        $classProperties = $classRef->getProperties(\ReflectionProperty::IS_PRIVATE);
        // 类实例
        $classInstance = $classRef->newInstance();
        foreach ($classProperties as $classProperty) {
            $classPropertyTypeName = $this->getVariableTypeName($classProperty->getType());
            // 如果非强类型 则跳过处理
            if ($classPropertyTypeName === null ) {
                continue;
            }
            $classPropertyMehtodName = $this->filterMethodName($classProperty->getName());
            try {
                $method = $classRef->getMethod('set' . $classPropertyMehtodName);
            }catch (\ReflectionException $e) {
                // 不存在setXxx方法 跳过
                continue;
            }
            // 尝试反射实体类
            try {
                $subClassRef = ReflectionManager::reflectClass($classPropertyTypeName);
                // 反射成功 为实体类 如果dataSource里边有对应属性，继续调用本方法实现子实体类的数据设置
                if (isset($dataSource[$classProperty->getName()])) {
                    $subObj = $this->setEntityClass($classPropertyTypeName, $dataSource[$classProperty->getName()]);
                    $method->invoke($classInstance, $subObj);
                }
            }catch (\InvalidArgumentException $e) {
                // 该类为基础数据类型/接口/trais等, 调用setter方法设置数据
                // 属性转驼峰
                var_dump('dataSource', $dataSource, $className);
                $method->invoke($classInstance, $dataSource[$classProperty->getName()]);
            }
        }
        return $classInstance;
    }

    /**
     * @param \ReflectionProperty[] $subClassProperties
     * @param \object $subClassInstance
     * @param array $mapData
     * @param ReflectionMethod[] $methodsData
     */
    private function setSubClass(array $subClassProperties, object $subClassInstance, array $mapData, array $methodsData)
    {
        echo PHP_EOL . PHP_EOL;
        echo "当前查询类:" . get_class($subClassInstance) . PHP_EOL;
        $subClassInstanceRef = ReflectionManager::reflectClass(get_class($subClassInstance));
        foreach ($subClassProperties as $key => $classProperty) {
            $typeName = $this->getVariableTypeName($classProperty->getType());
            echo "当前类{$key}属性:" . $typeName . PHP_EOL;
            try {
                $class = ReflectionManager::reflectClass($typeName);
            } catch (\InvalidArgumentException $e) {
                // 类属性不是一个实体类 调用setter 进行参数注入
                foreach ($methodsData as $datum) {
                    if (preg_match('/^set(\\w+)/', $datum->getName(), $matches)) {
                      $subClassInstance = $this->invokeSetterMethod($matches[1], $subClassInstanceRef, $subClassInstance, $mapData);
                    }
                }
                continue;
            }
            echo "获取" . $class->getName() . "类所有方法" . PHP_EOL;
            $methods = $class->getMethods();
            foreach ($methods as $method) {
                // 匹配类的setter方法
                if (preg_match('/^set(\\w+)/', $method->getName(), $matches)) {
                    // 转换变量名
                    $filter = strtolower($this->filterMethodName($matches[1]));
                    // 获取所有私有类属性
                    $properties = $class->getProperties(\ReflectionProperty::IS_PRIVATE);
                    foreach ($properties as $property) {
                        if (strtolower($property->getName()) === $filter) {
                            $className = $this->getVariableTypeName($property->getType());
                            try {
                                $method = $class->getMethod('set' . $matches[1]);
                            } catch (\ReflectionException $e) {
                                continue;
                            }
                            $methodArgs = $method->getParameters();
                            // setter方法有且仅有一个参数 并且请求参数有该数据 则执行setter
                            if (count($methodArgs) === 1 && isset($mapData[$filter])) {
                                if ($className === null) {
                                    // 私有属性不是一个类
                                    try {
                                        $method->invoke($subClassInstance, $mapData[$filter]);
                                    } catch (\ReflectionException $e) {
                                        continue;
                                    }
                                    var_dump('methodsInvoke', $subClassInstance);
                                } else {
                                    // 私有属性是一个类 invoke 类结果
                                    try {
                                        $subClass = ReflectionManager::reflectClass($className);
                                    } catch (\InvalidArgumentException|\ReflectionException $e) {
                                        continue;
                                    }
                                    $subClassProperties = $subClass->getProperties(\ReflectionProperty::IS_PRIVATE);
                                    try {
                                        $subClassInstance = $subClass->newInstance();
                                        $this->setSubClass($subClassProperties, $subClassInstance, $mapData[$property->getName()]);
                                        $method->invoke($subClassInstance, $subClassInstance);
                                    } catch (\ReflectionException $e) {
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    private function invokeSetterMethod(string $methodName, \ReflectionClass $newClass, object $classInstance, array $mapData)
    {
        $filter = strtolower($this->filterMethodName($methodName));
        $method = $newClass->getMethod('set' . $filter);
        $method = $class->getMethod('set' . $name);
        $args = $method->getParameters();
        if (count($args) === 1 && isset($mapData[$filter])) {
            $method->invoke($newClass, $mapData[$methodName]);
        }
        return $classInstance;
    }

    // 下划线转驼峰命名 采用正则逆序环视
    private function filterMethodName(string $methodName): string
    {
        return preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $methodName);
    }

    // 调用实体类setter方法进行对属性的数据设置
    private function invokeSetterMethods(string $name, \ReflectionClass $class, object &$newClass, array $mapData)
    {
        $filter = strtolower($this->filterMethodName($name));
        $properties = $class->getProperties(\ReflectionProperty::IS_PRIVATE);
        foreach ($properties as $property) {
            if (strtolower($property->getName()) === $filter) {
                // todo 尝试二次反射判断数据类型
                try {
                    $className = $this->getVariableTypeName($property->getType());
                    if ($className != null) {
                        $propertyClass = new \ReflectionClass($className);
                        if (! $propertyClass->isInterface()) {
                            $instance = $propertyClass->newInstance();
                        }
                    }
                } catch (\ReflectionException $e) {
                }
                try {
                    $method = $class->getMethod('set' . $name);
                    $args = $method->getParameters();
                    if (count($args) === 1 && isset($mapData[$filter])) {
                        $method->invoke($newClass, $mapData[$filter]);
                    }
                } catch (\ReflectionException $e) {
                    continue;
                }
            }
        }
    }

    private function getVariableTypeName(?\ReflectionNamedType $reflectionNamedType): ?string
    {
        if ($reflectionNamedType === null) {
            return null;
        }
        return $reflectionNamedType->getName();
    }
}
