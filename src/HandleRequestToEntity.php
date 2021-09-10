<?php

namespace Caterpillar\HyperfAnnotationParseBody;


use App\Aop\Annotation\ParseBody;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Exception\Exception;
use Hyperf\HttpServer\Contract\RequestInterface;

class HandleRequestToEntity
{
    // 要切入的注解
    public $annotations = [
        ParseBody::class,
    ];

    #[Inject]
    protected RequestInterface $request;

    private array $mapData = [];

    /**
     * @throws Exception
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $controllerMethod = $proceedingJoinPoint->getReflectMethod();
        $args = $controllerMethod->getParameters();
        $this->mapData = $this->request->all();
        // 循环判断即将执行的方法参数
        foreach ($args as $arg) {
            try {
                $class = new \ReflectionClass($arg->getType()->getName());
                // 遇到接口类直接跳过
                if ($class->isInterface()) {
                    continue;
                }
                // 尝试获取类反射实例
                $newClass = $class->newInstance();
            } catch (\ReflectionException) {
                // 如果反射失败 比如是基础数据类型 int string bool 等数据类型，则从请求参数里边获取对应的数据，如果没有则设置为Null
                $value = $this->mapData[$arg->getName()] ?? null;
                $type = $arg->getType()?->getName();
                // 兼容方法强类型
                if ($type && $value) {
                    settype($value, $type == 'int' ? 'integer' : $type);
                }
                // 设置方法参数值
                $proceedingJoinPoint->arguments['keys'][$arg->getName()] = $value;
                continue;
            }
            $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
            // 实体类set方法调用
            foreach ($methods as $method) {
                if (preg_match('/^set(\\w+)/', $method->getName(), $matches)) {
                    $this->invokeSetterMethods($matches[1], $class, $newClass);
                }
            }
            // 对实体类参数进行赋值
            $proceedingJoinPoint->arguments['keys'][$arg->getName()] = $newClass;
        }
        return $proceedingJoinPoint->process();
    }

    // 下划线转驼峰命名 采用正则逆序环视
    private function filterMethodName(string $methodName): string
    {
        return preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $methodName);
    }

    // 调用实体类setter方法进行对属性的数据设置
    private function invokeSetterMethods(string $name, \ReflectionClass $class, object &$newClass)
    {
        $filter = strtolower($this->filterMethodName($name));
        $properties = $class->getProperties(\ReflectionProperty::IS_PRIVATE);
        foreach ($properties as $property) {
            if (strtolower($property->getName()) === $filter) {
                try {
                    $method = $class->getMethod('set' . $name);
                    $args = $method->getParameters();
                    if (count($args) === 1 && isset($this->mapData[$filter])) {
                        $method->invoke($newClass, $this->mapData[$filter]);
                    }
                } catch (\ReflectionException) {
                    continue;
                }
            }
        }
    }
}