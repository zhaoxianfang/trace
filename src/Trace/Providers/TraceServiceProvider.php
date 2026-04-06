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
     * 标记是否已注册异常处理器，防止重复初始化
     */
    private static bool $exceptionHandlerRegistered = false;

    /**
     * 启动服务（在所有服务注册后调用）
     *
     * 执行时机：应用启动时
     */
    public function boot(): void
    {
        // 加载视图目录（从包内加载，不发布）
        $this->loadViewsFrom(__DIR__ . '/../../Resources/views', 'trace');

        // 加载 Trace 路由文件
        $this->loadRoutesFrom(__DIR__.'/../routes/trace.php');

        // 注册 Trace 中间件到全局中间件栈
        $this->registerMiddleware(TraceMiddleware::class);

        // 发布配置文件到项目配置目录
        $this->publishes([
            __DIR__ . '/../../../config/trace.php' => config_path('trace.php'),
        ], ['trace']);

        // 将 zxf/trace 版本信息添加到 Laravel about 命令输出中
        AboutCommand::add('Extend', [
            'zxf/trace' => fn () => InstalledVersions::getPrettyVersion('zxf/trace') ?? 'unknown',
        ]);
    }

    /**
     * 注册服务（在启动前调用）
     *
     * 执行时机：服务容器注册阶段
     */
    public function register(): void
    {
        // 注册路由服务提供者
        $this->app->register(RouteServiceProvider::class);

        // 注册 Trace 处理器为单例
        $this->registerTraceHandle();

        // 注册异常处理器（带防止重复初始化检查）
        $this->registerExceptionHandler();
    }

    /**
     * 注册 Trace 处理器
     *
     * @return void
     */
    private function registerTraceHandle(): void
    {
        // 如果已经注册，直接返回
        if ($this->app->bound('trace')) {
            return;
        }

        $this->app->singleton(Handle::class, function ($app) {
            return new Handle($app);
        });

        $this->app->alias(Handle::class, 'trace');
    }

    /**
     * 注册异常处理器
     *
     * 注意：此方法带重复初始化保护，确保只注册一次
     *
     * @return void
     */
    private function registerExceptionHandler(): void
    {
        // 检查是否已经注册过
        if (self::$exceptionHandlerRegistered) {
            return;
        }

        // 检查 Laravel 版本
        $laravelVersion = $this->app->version();
        $isLaravel11OrHigher = version_compare($laravelVersion, '11.0.0', '>=');

        if ($isLaravel11OrHigher) {
            // Laravel 11+ 使用新的异常处理机制
            // 仅在控制台或测试环境注册传统异常处理器
            // Web 环境通过 bootstrap/app.php 的 withExceptions() 配置
            if ($this->app->runningInConsole() || $this->app->environment('testing')) {
                $this->registerLegacyExceptionHandler();
                self::$exceptionHandlerRegistered = true;
            }
        } else {
            // Laravel 10 及以下版本，注册传统异常处理器
            $this->registerLegacyExceptionHandler();
            self::$exceptionHandlerRegistered = true;
        }
    }

    /**
     * 注册传统的异常处理器
     *
     * @return void
     */
    private function registerLegacyExceptionHandler(): void
    {
        $this->app->singleton(ExceptionHandler::class, function ($app) {
            // 尝试获取 Laravel 原始的异常处理器
            try {
                $originalHandler = $app->make(\Illuminate\Foundation\Exceptions\Handler::class);
            } catch (\Throwable $e) {
                // 返回一个最小实现的处理器
                $originalHandler = new class implements \Illuminate\Contracts\Debug\ExceptionHandler {
                    public function report(\Throwable $e): void {}
                    public function render($request, \Throwable $e): \Symfony\Component\HttpFoundation\Response {
                        return response()->json(['error' => $e->getMessage()], 500);
                    }
                    public function renderForConsole($output, \Throwable $e): void {}
                    public function shouldReport(\Throwable $e): bool {
                        return true;
                    }
                };
            }

            // 返回包装后的 Trace 异常处理器
            return new TraceExceptionHandler($originalHandler);
        });
    }

    /**
     * 注册中间件并全局启用
     *
     * @param  string  $middleware  中间件类名
     */
    protected function registerMiddleware(string $middleware): void
    {
        // 获取 HTTP 内核实例
        $kernel = $this->app->make(Kernel::class);

        // 将中间件添加到全局中间件栈的最前面
        $kernel->prependMiddleware($middleware);
    }

    /**
     * 获取服务提供者提供的服务列表
     *
     * @return array
     */
    public function provides(): array
    {
        return [];
    }
}
