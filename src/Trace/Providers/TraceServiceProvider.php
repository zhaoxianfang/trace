<?php

namespace zxf\Trace\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Console\AboutCommand;
use Composer\InstalledVersions;

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
