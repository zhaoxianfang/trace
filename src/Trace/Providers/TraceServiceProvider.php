<?php

namespace zxf\Trace\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Composer\InstalledVersions;
use zxf\Trace\Handle;
use zxf\Trace\Middleware\TraceMiddleware;
use zxf\Trace\TraceExceptionHandler;

/**
 * Trace 服务提供者
 *
 * 负责：
 * 1. 注册 Trace 服务和异常处理器
 * 2. 加载路由和视图
 * 3. 注册全局中间件
 * 4. 发布配置文件
 */
class TraceServiceProvider extends ServiceProvider
{
    /**
     * 服务提供者是否延迟加载（false 表示立即加载）
     */
    protected bool $defer = false;

    /**
     * 启动服务（在所有服务注册后调用）
     *
     * 执行时机：应用启动时
     */
    public function boot(): void
    {
        // 加载视图目录（从包内加载，不发布）
        // 视图别名：trace
        $this->loadViewsFrom(__DIR__ . '/../../Resources/views', 'trace');

        // 加载 Trace 路由文件
        $this->loadRoutesFrom(__DIR__.'/../routes/trace.php');

        // 注册 Trace 中间件到全局中间件栈
        $this->registerMiddleware(TraceMiddleware::class);

        // 发布配置文件到项目配置目录
        // 标签：trace
        $this->publishes([
            __DIR__ . '/../../../config/trace.php' => config_path('trace.php'),
        ], ['trace']);

        // 将 zxf/trace 版本信息添加到 Laravel about 命令输出中
        AboutCommand::add('Extend', [
            'zxf/trace' => fn () => InstalledVersions::getPrettyVersion('zxf/trace'),
        ]);
    }

    /**
     * 注册服务（在启动前调用）
     *
     * 执行时机：服务容器注册阶段
     *
     * 注意：
     * 1. Trace 处理器注册为单例，确保整个请求周期内使用同一个实例
     * 2. 异常处理器也注册为单例，避免重复创建
     * 3. 两者协同工作：TraceExceptionHandler 包装原始异常处理器
     */
    public function register(): void
    {
        // 注册路由服务提供者
        $this->app->register(RouteServiceProvider::class);

        // 注册 Trace 处理器为单例（app('trace')）
        // 注意：Handle 单例会被多个请求共享，因此内部使用 requestId 进行状态隔离
        $this->app->singleton(Handle::class, function ($app) {
            return new Handle($app);
        });
        // 设置别名，可以通过 app('trace') 访问
        $this->app->alias(Handle::class, 'trace');

        // 注册自定义异常处理器为单例
        // 方式：单次注册，接管 Laravel 默认的异常处理
        // 注意：Laravel 11+ 中，用户可以在 bootstrap/app.php 的 withExceptions() 中进一步配置
        // 这不会导致重复处理，因为 TraceExceptionHandler 已经接管了异常处理流程
        $this->app->singleton(ExceptionHandler::class, function ($app) {
            // 获取 Laravel 原始的异常处理器
            $originalHandler = $app->make(\Illuminate\Foundation\Exceptions\Handler::class);

            // 返回包装后的 Trace 异常处理器
            return new TraceExceptionHandler($originalHandler);
        });
    }

    /**
     * 注册中间件并全局启用
     *
     * @param  string  $middleware  中间件类名
     */
    protected function registerMiddleware($middleware): void
    {
        // 获取 HTTP 内核实例
        $kernel = $this->app->make(Kernel::class);

        // 将中间件添加到全局中间件栈的最前面
        // 这样可以在所有其他中间件之前拦截请求
        $kernel->prependMiddleware($middleware);

        // 其他可选的中间件注册方式：
        // $kernel->pushMiddleware($middleware);              // 追加在后面
        // $kernel->appendMiddlewareToGroup('web', $middleware);  // 追加到 web 组
        // $kernel->prependMiddlewareToGroup('web', $middleware); // 放到 web 组最前面
    }

    /**
     * 获取服务提供者提供的服务列表
     *
     * @return array
     */
    public function provides(): array
    {
        // 返回空数组，因为我们已经显式注册了所有服务
        return [];
    }
}
