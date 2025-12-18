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

class TraceServiceProvider extends ServiceProvider
{
    /**
     * 服务提供者是否延迟加载
     */
    protected bool $defer = false;

    /**
     * 启动服务
     */
    public function boot(): void
    {
        // 加载视图（不发布，直接从包内访问）
        $this->loadViewsFrom(__DIR__ . '/../../Resources/views', 'trace');

        // 加载 trace 路由
        $this->loadRoutesFrom(__DIR__.'/../routes/trace.php');

        // 注册中间件
        $this->registerMiddleware(TraceMiddleware::class);

        $this->publishes([
            __DIR__ . '/../../../config/trace.php' => config_path('trace.php'),
        ], ['trace']);

        // 把 zxf/trace 添加到 about 命令中
        AboutCommand::add('Extend', [
            'zxf/trace' => fn () => InstalledVersions::getPrettyVersion('zxf/trace'),
        ]);
    }

    /**
     * 注册服务
     */
    public function register(): void
    {
        // 注册路由服务提供者
        $this->app->register(RouteServiceProvider::class);

        // 定义 app('trace')
        $this->app->singleton(Handle::class, function ($app) {
            return new Handle($app);
        });
        $this->app->alias(Handle::class, 'trace');

        // 方式一：单次注册
        $this->app->singleton(ExceptionHandler::class, function ($app) {
            // 获取原始处理器
            $originalHandler = $app->make(\Illuminate\Foundation\Exceptions\Handler::class);

            return new TraceExceptionHandler($originalHandler);
        });
    }

    /**
     * 注册中间件 并全局启用
     */
    protected function registerMiddleware($middleware): void
    {
        $kernel = $this->app->make(Kernel::class);
        // $kernel->pushMiddleware($middleware); // 追加在后面
        $kernel->prependMiddleware($middleware); // 放在最前面

        // 把中间件添加到web组
        // $kernel->appendMiddlewareToGroup('web', $middleware); // 追加在后面
        // $kernel->prependMiddlewareToGroup('web', $middleware);   // 放在最前面
    }

    /**
     * 获取服务提供者提供的服务
     */
    public function provides(): array
    {
        return [
        ];
    }
}
