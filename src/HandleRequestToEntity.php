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
use JetBrains\PhpStorm\Pure;

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
                $proceedingJoinPoint->arguments['keys'][$arg->getName()] = $value;
                continue;
            }
            // 实体类
            try {
                $newClass = $this->setEntityClass($variableTypeName, $mapData);
            } catch (VariableTypeNotObtained|\ReflectionException $e) {
                continue;
            }
            $proceedingJoinPoint->arguments['keys'][$arg->getName()] = $newClass;
        }
        return $proceedingJoinPoint->process();
    }

    /**
     * @throws \ReflectionException
     * @throws VariableTypeNotObtained
     */
    private function setEntityClass(string $className, array $dataSource): object
    {
        try {
            $classRef = ReflectionManager::reflectClass($className);
        } catch (\InvalidArgumentException $e) {
            throw new VariableTypeNotObtained('类不存在');
        }
        // 类私有属性
        $classProperties = $classRef->getProperties(\ReflectionProperty::IS_PRIVATE);
        // 类实例
        $classInstance = $classRef->newInstance();
        foreach ($classProperties as $classProperty) {
            $classPropertyTypeName = $this->getVariableTypeName($classProperty->getType());
            // 如果非强类型 则跳过处理
            if ($classPropertyTypeName === null) {
                continue;
            }
            $classPropertyMethodName = $this->filterMethodName($classProperty->getName());
            try {
                $method = $classRef->getMethod('set' . $classPropertyMethodName);
            } catch (\ReflectionException $e) {
                // 不存在setXxx方法 跳过
                continue;
            }
            // 尝试反射实体类
            try {
                ReflectionManager::reflectClass($classPropertyTypeName);
                // 反射成功 为实体类 如果dataSource里边有对应属性，继续调用本方法实现子实体类的数据设置
                if (isset($dataSource[$classProperty->getName()])) {
                    $subObj = $this->setEntityClass($classPropertyTypeName, $dataSource[$classProperty->getName()]);
                    $method->invoke($classInstance, $subObj);
                }
            } catch (\InvalidArgumentException $e) {
                // 该类为基础数据类型/接口/trait , 调用setter方法设置数据
                // 属性转驼峰
                $method->invoke($classInstance, $dataSource[$classProperty->getName()]);
            }
        }
        return $classInstance;
    }

    // 下划线转驼峰命名 采用正则逆序环视
    private function filterMethodName(string $methodName): string
    {
        return preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $methodName);
    }

    #[Pure]
    private function getVariableTypeName(?\ReflectionNamedType $reflectionNamedType): ?string
    {
        if ($reflectionNamedType === null) {
            return null;
        }
        return $reflectionNamedType->getName();
    }
}
