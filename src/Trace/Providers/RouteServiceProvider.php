<?php

namespace zxf\Trace\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

/**
 * Trace 路由服务提供者
 *
 * 负责：
 * 1. 注册资源文件路由（CSS、JS、图片、字体等）
 * 2. 提供安全的文件访问控制
 * 3. 实现文件缓存机制
 *
 * 注意：此路由提供者已被弃用，建议使用 routes/trace.php 中的路由定义
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
        // 注册资源文件路由（已弃用，建议使用 routes/trace.php）
        $this->mapAssetRoutes();
    }

    /**
     * 注册资源文件路由（已弃用）
     *
     * @deprecated 建议使用 routes/trace.php 中的路由定义
     */
    protected function mapAssetRoutes(): void
    {
        // 创建专门用于资源文件的路由组
        // 使用 web 中间件，但排除安全相关中间件
        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(function () {
                // CSS 文件路由
                Route::get('/zxf/trace/css/{file}', function ($file) {
                    return $this->serveAsset('css', $file);
                })->where('file', '.*\.css$')->name('trace.assets.css');

                // JS 文件路由
                Route::get('/zxf/trace/js/{file}', function ($file) {
                    return $this->serveAsset('js', $file);
                })->where('file', '.*\.js$')->name('trace.assets.js');

                // 图片文件路由
                Route::get('/zxf/trace/images/{file}', function ($file) {
                    return $this->serveAsset('images', $file);
                })->where('file', '.*\.(png|jpg|jpeg|gif|svg|ico)$')->name('trace.assets.images');

                // 字体文件路由
                Route::get('/zxf/trace/fonts/{file}', function ($file) {
                    return $this->serveAsset('fonts', $file);
                })->where('file', '.*\.(woff|woff2|ttf|eot)$')->name('trace.assets.fonts');
            });
    }

    /**
     * 提供资源文件（带安全检查和缓存）
     *
     * 功能：
     * 1. 防止目录遍历攻击
     * 2. 设置正确的 Content-Type
     * 3. 实现浏览器缓存（ETag、Cache-Control）
     *
     * @param  string  $type  资源类型（css、js、images、fonts）
     * @param  string  $file  文件名
     *
     * @return \Illuminate\Http\Response
     */
    protected function serveAsset(string $type, string $file): \Illuminate\Http\Response
    {
        // 获取当前请求对象
        $request = app(Request::class);

        // 构建资源文件的基础路径
        $basePath = __DIR__ . "/../../Resources/{$type}";
        $path = "{$basePath}/{$file}";

        // 安全检查：防止目录遍历攻击
        $realBasePath = realpath($basePath);
        $realPath = realpath($path);

        // 如果路径不在允许的范围内，返回 404
        if (!$realPath || !str_starts_with($realPath, $realBasePath)) {
            abort(404, "资源文件未找到: {$file}");
        }

        // 检查文件是否存在
        if (!file_exists($path) || !is_file($path)) {
            abort(404, "资源文件未找到: {$file}");
        }

        // MIME 类型映射表
        $mimeTypes = [
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'eot'   => 'application/vnd.ms-fontobject',
        ];

        // 获取文件扩展名并设置 Content-Type
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $contentType = $mimeTypes[$extension] ?? 'text/plain';

        // 设置缓存相关的响应头
        $headers = [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=31536000', // 1年缓存（31,536,000秒）
            'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000),
        ];

        // 添加 ETag 用于客户端缓存验证
        $etag = md5($path . filemtime($path));
        $headers['ETag'] = $etag;

        // 检查客户端是否已有缓存的版本（仅当 request 可用时）
        $clientEtag = null;
        if ($request) {
            $clientEtag = $request->header('If-None-Match');
        }
        if ($clientEtag && $clientEtag === $etag) {
            // 返回 304 Not Modified，不发送文件内容
            return response('', 304, $headers);
        }

        // 返回文件内容
        return response()
            ->file($path, $headers);
    }
}
