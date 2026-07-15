<?php

namespace zxf\Trace\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use zxf\Trace\AssetController;

/**
 * Trace 路由服务提供者
 *
 * 负责：
 * 1. 注册资源文件路由（CSS、JS、图片、字体等）
 * 2. 提供安全的文件访问控制
 * 3. 实现文件缓存机制
 *
 * 重要（修复 route:cache 内存耗尽）：
 * 旧版本在 mapAssetRoutes() 中使用「闭包路由」：
 *   Route::get('/zxf/trace/css/{file}', function ($file) {
 *       return $this->serveAsset('css', $file);
 *   });
 * 该闭包在类方法内定义，隐式绑定了 $this（即本服务提供者实例，
 * 而服务提供者持有完整的 Laravel 应用容器）。执行
 * `php artisan route:cache` / `php artisan optimize` 时，框架会调用
 * Route::prepareForSerialization() -> SerializableClosure::unsigned() 对每个路由
 * 进行序列化；序列化闭包会递归序列化其绑定的 $this（整个应用容器），
 * 在低配服务器（memory_limit 通常为 1G）上直接导致
 *   PHP Fatal error: Allowed memory size ... exhausted ... Route.php on line 1484
 *
 * 本版本改用「控制器方式」注册静态资源路由，路由动作是可被安全序列化的
 * 控制器引用（zxf\Trace\AssetController），route:cache 即可正常工作。
 */
class RouteServiceProvider extends ServiceProvider
{
    /**
     * 启动服务
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * 注册路由
     */
    public function map(): void
    {
        $this->mapAssetRoutes();
    }

    /**
     * 注册资源文件路由（控制器方式，可安全缓存）。
     *
     * 使用 zxf\Trace\AssetController 提供资源文件，避免闭包路由在
     * route:cache 时递归序列化应用容器导致内存耗尽。
     */
    protected function mapAssetRoutes(): void
    {
        $controller = AssetController::class;

        // 使用 web 中间件，但排除安全相关中间件（保持原行为）
        Route::middleware('web')
            ->group(function () use ($controller) {
                // CSS 文件路由
                Route::get('/zxf/trace/css/{file}', [$controller, 'cssServe'])
                    ->where('file', '.*\.css$')
                    ->name('trace.assets.css');

                // JS 文件路由
                Route::get('/zxf/trace/js/{file}', [$controller, 'jsServe'])
                    ->where('file', '.*\.js$')
                    ->name('trace.assets.js');

                // 图片文件路由
                Route::get('/zxf/trace/images/{file}', [$controller, 'imageServe'])
                    ->where('file', '.*\.(png|jpg|jpeg|gif|svg|ico)$')
                    ->name('trace.assets.images');

                // 字体文件路由
                Route::get('/zxf/trace/fonts/{file}', [$controller, 'fontServe'])
                    ->where('file', '.*\.(woff|woff2|ttf|eot)$')
                    ->name('trace.assets.fonts');
            });
    }
}
