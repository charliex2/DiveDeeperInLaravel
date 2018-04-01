# Dive deeper into Laravel
 用于学习 Laravel 的核心概念和源码
 
 ## Laravel 的 Container
 在 Laravel 框架中，服务容器通过 Illuminate\Container\Container 类来实现。
 但 Laravel 的 Container 实现更加复杂， 其除了完成了 IoC 容器的功能外，还在程序允许过程中提供各种相应的服务，包括对象、生成对象的回调函数、配置等。
