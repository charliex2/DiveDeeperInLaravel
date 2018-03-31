<?php
/**
 * Created by PhpStorm.
 * User: W
 * Date: 01/04/2018
 * Time: 00:46
 */

class Container
{

    protected $bindings = [];

    // 将一个具体的实物绑定给一个缩写
    public function bind($abstract, $concrete = null, $shared = false)
    {
        if (!$concrete instanceof Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }
        $this->bindings[$abstract] = compact('concrete', 'shared');
    }

    protected function getClosure($abstract, $concrete)
    {
        return function ($c) use ($abstract, $concrete) {
            // 这里啥意思
            $method = ($abstract == $concrete) ? 'build' : "make";
            // 调用 make 或者 build 方法生成实例
            return $c->$method($concrete);
        };
    }

    public function make($abstract)
    {
        $concrete = $this->getConcrete($abstract);
        if ($this->isBuildable($concrete, $abstract)) {
            $object = $this->build($concrete);
        } else {
            $object = $this->make($concrete);
        }
        return $object;
    }

    // 从bindings 数组中取得相应的回调函数
    protected function getConcrete($abstract)
    {
        if (!isset($this->bindings[$abstract])) {
            return $abstract;
        }
        return $this->bindings[$abstract]['concrete'];
    }

    protected function isBuildable($concrete, $abstract)
    {
        // $concrete === $abstract 是什么意思？
        return $concrete === $abstract || $concrete instanceof Closure;
    }

    // 实例化对象

    /**
     * @param $concrete
     * @return mixed
     */
    public function build($concrete)
    {
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        $reflector = null;
        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
        }
        if (!$reflector->isInstantiable()) {
            echo $message = "Target [$concrete] is not instantiable";
        }

        $constructor = $reflector->getConstructor();

        // 如果没有 constructor 函数，那么就没有其他依赖
        if (is_null($constructor)) {
            return new $concrete;
        }

        $dependencies = $constructor->getParameters();

        $instances = $this->getDependencies($dependencies);

        // 从给出的参数创建一个新的类实例
        return $reflector->newInstanceArgs($instances);

    }

    protected function getDependencies($parameters)
    {
        $dependencies = [];
        foreach ($parameters as $parameter) {
            $dependency = $parameter->getClass();
            if (is_null($dependency)) {
                $dependencies[] = null;
            } else {
                $dependencies[] = $this->resolveClass($parameter);
            }
            return (array)$dependencies;
        }
    }

    protected function resolveClass(ReflectionParameter $parameter)
    {
        return $this->make($parameter->getClass()->name);
    }

}

Class Traveller
{
    protected $trafficTool;

    /**
     * Traveller constructor.
     * @param $trafficTool
     */
    public function __construct(Visit $trafficTool)
    {
        $this->trafficTool = $trafficTool;
    }

    public function visitTibet()
    {
        $this->trafficTool->go();
    }
}

$app = new Container();
$app->bind("Visit", "Train");
$app->bind('traveller', "Traveller");

$tra = $app->make("traveller");
$tra->visitTibet();
