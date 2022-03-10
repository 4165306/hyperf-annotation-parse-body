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
namespace Caterpillar\HyperfAnnotationParseBody\Middleware;

use Hyperf\Di\ClosureDefinitionCollectorInterface;
use Closure;

class ParseBodyCoreMiddleware extends \Hyperf\HttpServer\CoreMiddleware
{

    public function __construct(ContainerInterface $container, string $serverName = 'http')
    {
        parent::__construct($container, $serverName);
    }
    /**
     * Parse the parameters of method definitions, and then bind the specified arguments or
     * get the value from DI container, combine to a argument array that should be injected
     * and return the array.
     */
    protected function parseMethodParameters(string $controller, string $action, array $arguments): array
    {
        $definitions = $this->getMethodDefinitionCollector()->getParameters($controller, $action);
        return $this->getInjections($definitions, "{$controller}::{$action}", $arguments);
    }

    /**
     * Parse the parameters of closure definitions, and then bind the specified arguments or
     * get the value from DI container, combine to a argument array that should be injected
     * and return the array.
     */
    protected function parseClosureParameters(Closure $closure, array $arguments): array
    {
        if (! $this->container->has(ClosureDefinitionCollectorInterface::class)) {
            return [];
        }
        $definitions = $this->getClosureDefinitionCollector()->getParameters($closure);
        return $this->getInjections($definitions, 'Closure', $arguments);
    }

    private function getInjections(array $definitions, string $callableName, array $arguments): array
    {
        $injections = [];
        foreach ($definitions ?? [] as $pos => $definition) {
            $value = $arguments[$pos] ?? $arguments[$definition->getMeta('name')] ?? null;
            if ($value === null) {
                if ($definition->getMeta('defaultValueAvailable')) {
                    $injections[] = $definition->getMeta('defaultValue');
                } elseif ($definition->allowsNull()) {
                    $injections[] = null;
                } elseif ($this->container->has($definition->getName())) {
                    $injections[] = $this->container->get($definition->getName());
                } else {
                    $type = $definition->getName();
                    $defaultValue = $definition->getMeta('defaultValue');
                    settype($defaultValue, $type === 'int' ? 'integer' : $type);
                    $injections[] = $defaultValue;
                }
            } else {
                $injections[] = $this->getNormalizer()->denormalize($value, $definition->getName());
            }
        }
        return $injections;
    }
}
