<?php
/**
 * Created by PhpStorm.
 * User: W
 * Date: 01/04/2018
 * Time: 02:26
 */

class LaravelContainer
{
    // 储存提供服务的回调函数
    protected $bindings = [];

    // 储存程序中共享实例
    protected $instances = [];

    protected $buildStack = [];

    public function singleton($abstract, $concrete)
    {
        $this->bind($abstract, $concrete, true);
    }

    // 注册一个绑定到容器，shared=true 表示一个共享的绑定，即 singleton
    public function bind($abstract, $concrete, $true)
    {
        if (is_array($abstract)) {
            list($abstract, $alias) = $this->extractAlias($abstract);
            $this->alias($abstract, $alias);
        }
        $this->dropStableInstances($abstract);

        if (is_null($concrete)) {
            $concrete = $abstract;
        }

        if (!$concrete instanceof Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }
        $this->bindings[$abstract] = compact('concrete', 'shared');
        if ($this->resolved($abstract)) {
            $this->rebound($abstract);
        }
    }

    // 得到一个闭包用于绑定一个类
    protected function getClosure($abstract, $concrete)
    {
        return function ($container, $parameters = []) use ($abstract, $concrete) {
            $method = ($concrete === $abstract) ? "build" : "make";
            return $container->$method($concrete, $parameters);
        };
    }

    public function make($abstract, $parameters = [])
    {
        $abstract = $this->getAlias($abstract);
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }
        $concrete = $this->getConcrete($abstract);
        if ($this->isBuildable($concrete, $abstract)) {
            $object = $this->build($concrete, $parameters);
        } else {
            $object = $this->make($concrete, $parameters);
        }

        foreach ($this->getExtenders($abstract) as $extender) {
            $object = $extender($object, $this);
        }

        if ($this->isShared($abstract)) {
            $this->instances[$abstract] = $object;
        }
        $this->fireResolvingCallbacks($abstract, $object);
        $this->resolved[$abstract] = true;
        return $object;
    }

    public function build($concrete, $parameters = [])
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        $reflector = new ReflectionClass($concrete);
        if (!$reflector->isInstantiable()) {
            $message = "Target [$concrete] is not instantiable.";
            throw new BindingResolutionException($message);
        }
        $this->buildStack[] = $concrete;
        $constructor = $reflector->getConstructor();

        // 如果没有构造函数
        if (is_null($constructor)) {
            array_pop($this->buildStack);
            return new $concrete;
        }

        $dependencies = $constructor->getParameters();
        $parameters = $this->keyParametersByArgument($dependencies, $parameters);
        $instances = $this->getDependencies($dependencies, $parameters);

        array_pop($this->buildStack);
        return $reflector->newInstanceArgs($this->instances);
    }

    public function getDependencies($parameters, array $primitives = [])
    {
        $dependencies = [];
        foreach ($parameters as $parameter) {
            $dependency = $parameter->getClass();
            if (key_exists($parameter->name, $primitives)) {
                $dependencies[] = $primitives[$parameter->name];
            } elseif (is_null($dependency)) {
                $dependencies[] = $this->resolveNonClass($parameter);
            } else {
                $dependencies[] = $this->resolveClass($parameter);
            }
        }
        return (array)$dependencies;
    }

    // 解决一个没有类提示的依赖
    protected function resolveNonClass(ReflectionParameter $parameter)
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }
    }

    protected function resolveClass(ReflectionParameter $parameter)
    {
        try {
            return $this->make($parameter->getClass()->name);
        } catch (Exception $e) {

        }
    }


}