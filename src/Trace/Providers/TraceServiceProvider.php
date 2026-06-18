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
use zxf\Trace\FallbackExceptionHandler;
use zxf\Trace\EmergencyRenderer;
use zxf\Trace\ViewGuard;

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
        // 注册视图命名空间
        $this->registerViewNamespace();

        // 加载 Trace 路由文件
        $this->loadRoutesFrom(__DIR__.'/../routes/trace.php');

        // 注册 Trace 中间件到全局中间件栈
        $this->registerMiddleware(TraceMiddleware::class);

        // 发布配置文件到项目配置目录
        $this->publishes([
            __DIR__ . '/../../../config/trace.php' => config_path('trace.php'),
        ], ['trace', 'trace-config']);

        // 发布视图文件（可选）
        $this->publishes([
            __DIR__ . '/../../Resources/views' => resource_path('views/vendor/trace'),
        ], ['trace', 'trace-views']);

        // 将 zxf/trace 版本信息添加到 Laravel about 命令输出中
        AboutCommand::add('Extend', [
            'zxf/trace' => fn () => InstalledVersions::getPrettyVersion('zxf/trace') ?? 'unknown',
        ]);

        // 标记 Laravel 已启动完成
        FallbackExceptionHandler::markLaravelBooted();

        // 注册视图保护（确保内部视图只能被 Trace 包访问）
        ViewGuard::register();
    }

    /**
     * 注册视图命名空间
     *
     * 注册 'trace' 命名空间，使得可以使用 trace:: 前缀访问扩展包的视图
     *
     * @return void
     */
    private function registerViewNamespace(): void
    {
        try {
            $viewPath = __DIR__ . '/../../Resources/views';

            // 验证视图目录是否存在
            if (!is_dir($viewPath)) {
                EmergencyRenderer::logError("Trace view path not found: {$viewPath}", 'ViewNamespace');
                return;
            }

            // 使用 loadViewsFrom 注册命名空间
            $this->loadViewsFrom($viewPath, 'trace');

            // 如果应用视图目录中存在自定义视图，则优先使用
            $customViewPath = resource_path('views/vendor/trace');
            if (is_dir($customViewPath)) {
                // 确保视图工厂已绑定
                if ($this->app->bound('view')) {
                    $this->app->make('view')->prependNamespace('trace', $customViewPath);
                }
            }
        } catch (\Throwable $e) {
            // 记录错误但不中断应用启动
            EmergencyRenderer::logError($e, 'ViewNamespaceRegistration');
        }
    }

    /**
     * 注册服务（在启动前调用）
     *
     * 执行时机：服务容器注册阶段
     *
     * 注意：当 trace.enabled 为 false 时，Handle 仍会注册（供异常处理器使用），
     * 但 Handle 内部会跳过所有调试数据收集（SQL/Model/View/Route），
     * output() 返回空字符串，从而大幅降低资源消耗。
     */
    public function register(): void
    {
        // 注册兜底异常处理器（最先注册，确保在引导阶段就能捕获错误）
        $this->registerFallbackHandler();

        // 注册路由服务提供者
        $this->app->register(RouteServiceProvider::class);

        // 注册 Trace 处理器为单例（异常处理器依赖此实例）
        $this->registerTraceHandle();

        // 注册异常处理器（带防止重复初始化检查）
        $this->registerExceptionHandler();

        // 注册引导阶段错误保护
        $this->registerBootstrapProtection();
    }

    /**
     * 注册兜底异常处理器
     *
     * 在 Laravel 框架完全加载之前就能捕获和处理异常
     *
     * @return void
     */
    private function registerFallbackHandler(): void
    {
        // 只在 Web 环境下注册
        if (PHP_SAPI === 'cli') {
            return;
        }

        // 注册兜底处理器
        FallbackExceptionHandler::register();
    }

    /**
     * 注册引导阶段错误保护
     *
     * 捕获配置加载、服务提供者初始化等引导阶段的错误
     *
     * @return void
     */
    private function registerBootstrapProtection(): void
    {
        // 监听容器解析错误
        $this->app->beforeResolving(function ($abstract, $parameters) {
            try {
                // 检查是否是缓存相关的类
                if (is_string($abstract) && str_contains($abstract, 'Cache')) {
                    // 预检查缓存配置
                    $this->validateCacheConfig();
                }
            } catch (\Throwable $e) {
                EmergencyRenderer::logError($e, 'BootstrapProtection');
            }
        });
    }

    /**
     * 验证缓存配置
     *
     * @return void
     * @throws \RuntimeException
     */
    private function validateCacheConfig(): void
    {
        try {
            $cachePath = storage_path('framework/cache');
            if (!is_dir($cachePath)) {
                return;
            }

            // 检查缓存目录是否可读
            if (!is_readable($cachePath)) {
                throw new \RuntimeException("Cache directory is not readable: {$cachePath}");
            }

            // 检查是否存在损坏的缓存文件（简单的文件大小检查）
            $files = glob($cachePath . '/*/*.php', GLOB_NOSORT);
            if ($files === false) {
                return;
            }

            foreach (array_slice($files, 0, 10) as $file) {
                if (!is_file($file) || filesize($file) === 0) {
                    // 发现空文件，可能是损坏的缓存
                    @unlink($file);
                }
            }
        } catch (\Throwable $e) {
            // 记录但不中断
            EmergencyRenderer::logError($e, 'CacheConfigValidation');
        }
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
            // 检查是否已经通过 bootstrap/app.php 配置了异常处理器
            $hasCustomExceptionHandler = $this->hasCustomExceptionHandler();

            if (! $hasCustomExceptionHandler) {
                // 如果没有自定义异常处理器，注册我们的处理器
                $this->registerLegacyExceptionHandler();
            }

            self::$exceptionHandlerRegistered = true;
        } else {
            // Laravel 10 及以下版本，注册传统异常处理器
            $this->registerLegacyExceptionHandler();
            self::$exceptionHandlerRegistered = true;
        }
    }

    /**
     * 检查是否已经配置了自定义异常处理器
     *
     * @return bool
     */
    private function hasCustomExceptionHandler(): bool
    {
        try {
            // 获取当前的异常处理器
            $currentHandler = $this->app->make(ExceptionHandler::class);

            // 如果已经是 TraceExceptionHandler，说明已经注册过
            if ($currentHandler instanceof \zxf\Trace\TraceExceptionHandler) {
                return true;
            }

            // 检查是否是 Laravel 的默认处理器
            if ($currentHandler instanceof \Illuminate\Foundation\Exceptions\Handler) {
                return false;
            }

            // 其他情况认为有自定义处理器
            return true;
        } catch (\Throwable $e) {
            // 如果无法获取异常处理器，认为没有自定义处理器
            return false;
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
