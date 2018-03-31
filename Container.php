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
        // 如果concrete不是一个闭包，则需要自己创建一个闭包函数
        if (!$concrete instanceof Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }
        $this->bindings[$abstract] = compact('concrete', 'shared');
    }

    protected function getClosure($abstract, $concrete)
    {
        // 该闭包函数的参数 $c 即为 Container 实例
        return function ($c) use ($abstract, $concrete) {
            // 如果$abstract 和 concrete 不相同，则调用 make 方法
            // 如果相同，则直接调用 build 方法
            $method = ($abstract == $concrete) ? 'build' : "make";
            // 调用 make 或者 build 方法生成实例
            return $c->$method($concrete);
        };
    }

    public function make($abstract)
    {
        //这里的 $abstract 其实是上一个方法 getClosure 的 $concrete 参数
        // 先用 getConcrete 函数去 bindings 里面找找看，如果找得到就返回，找不到就算了。
        $concrete = $this->getConcrete($abstract);

        // 检查一下能不能 Build
        if ($this->isBuildable($concrete, $abstract)) {
            $object = $this->build($concrete);
        } else {
            // 如果不能 build，也就是说在 $bindings 数组中 key 和 value 不一样，再调用 make 方法去找 以这个 value 为键的值是否存在
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
        // 判断的标准就是
        // 如果 $abstract 和 $concrete 相同，或者 $concrete 是一个闭包函数，那么就是 buildable
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
