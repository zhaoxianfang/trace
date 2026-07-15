<?php

namespace zxf\Trace;

use DateTime;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * 资源文件控制器
 *
 * 负责：
 * 1. 提供合并后的调试资源文件（css()/js()，由 routes/trace.php 调用）。
 * 2. 按需提供 Resources 目录下的单个静态资源文件（css/js/images/fonts）。
 *
 * 设计要点：
 * - 采用「控制器方法」而非「闭包路由」提供资源，确保
 *   `php artisan route:cache` / `php artisan optimize` 能安全地序列化路由。
 *   闭包路由会隐式绑定 $this（服务提供者持有完整应用容器），
 *   序列化时递归展开整个容器，在低配服务器上直接导致内存耗尽
 *   （Allowed memory size exhausted @ Route.php:1484）。
 * - 继承 Illuminate\Routing\Controller，符合 Laravel 约定，且能被
 *   route:cache 的控制器动作查找机制正确识别。
 */
class AssetController extends Controller
{
    /**
     * @var array JavaScript 文件列表
     */
    protected array $jsFiles = [];

    /**
     * @var array CSS 文件列表
     */
    protected array $cssFiles = [];

    /**
     * 构造函数 - 初始化资源文件路径
     */
    public function __construct()
    {
        // 定义 JavaScript 文件路径
        $this->jsFiles = [
            dirname(__DIR__, 1) . '/Resources/js/trace.js',
        ];
        // 定义 CSS 文件路径
        $this->cssFiles = [
            dirname(__DIR__, 1) . '/Resources/css/trace.css',
        ];
    }

    /**
     * 获取 JavaScript 调试文件（合并单个文件）
     *
     * 路由：zxf.trace.trace.js
     *
     * @return Response
     */
    public function js(): Response
    {
        $content = '';

        try {
            // 读取并合并所有 JavaScript 文件内容
            foreach ($this->jsFiles as $file) {
                if (file_exists($file) && is_readable($file)) {
                    $fileContent = file_get_contents($file);
                    if ($fileContent !== false) {
                        $content .= $fileContent . "\n";
                    }
                }
            }
        } catch (\Throwable $e) {
            // 文件读取失败，返回空内容或错误提示
            $content = "console.error('Trace: Failed to load JavaScript file');";
        }

        // 创建响应对象并设置 Content-Type
        $response = new Response($content, 200, [
            'Content-Type' => 'text/javascript; charset=utf-8',
        ]);

        // 设置缓存头（1年有效期）
        return $this->cacheResponse($response);
    }

    /**
     * 获取 CSS 调试文件（合并单个文件）
     *
     * 路由：zxf.trace.trace.css
     *
     * @return Response
     */
    public function css(): Response
    {
        $content = '';

        try {
            // 读取并合并所有 CSS 文件内容
            foreach ($this->cssFiles as $file) {
                if (file_exists($file) && is_readable($file)) {
                    $fileContent = file_get_contents($file);
                    if ($fileContent !== false) {
                        $content .= $fileContent . "\n";
                    }
                }
            }
        } catch (\Throwable $e) {
            // 文件读取失败，返回空内容或错误提示
            $content = '/* Trace: Failed to load CSS file */';
        }

        // 创建响应对象并设置 Content-Type
        $response = new Response($content, 200, [
            'Content-Type' => 'text/css; charset=utf-8',
        ]);

        // 设置缓存头（1年有效期）
        return $this->cacheResponse($response);
    }

    /**
     * 提供 Resources 目录下的单个静态资源文件（CSS）。
     *
     * 用于替代原 RouteServiceProvider 中的闭包路由，避免 route:cache 序列化容器。
     *
     * 路由：trace.assets.css
     */
    public function cssServe(string $file): SymfonyResponse
    {
        return $this->serve('css', $file);
    }

    /**
     * 提供 Resources 目录下的单个静态资源文件（JS）。
     *
     * 路由：trace.assets.js
     */
    public function jsServe(string $file): SymfonyResponse
    {
        return $this->serve('js', $file);
    }

    /**
     * 提供 Resources 目录下的单个静态资源文件（图片）。
     *
     * 路由：trace.assets.images
     */
    public function imageServe(string $file): SymfonyResponse
    {
        return $this->serve('images', $file);
    }

    /**
     * 提供 Resources 目录下的单个静态资源文件（字体）。
     *
     * 路由：trace.assets.fonts
     */
    public function fontServe(string $file): SymfonyResponse
    {
        return $this->serve('fonts', $file);
    }

    /**
     * 提供资源文件（带安全检查和缓存）。
     *
     * 功能：
     * 1. 防止目录遍历攻击
     * 2. 设置正确的 Content-Type
     * 3. 实现浏览器缓存（ETag、Cache-Control）
     *
     * @param  string  $type  资源类型（css、js、images、fonts）
     * @param  string  $file  文件名
     *
     * @return SymfonyResponse
     */
    protected function serve(string $type, string $file): SymfonyResponse
    {
        // 构建资源文件的基础路径（src/Trace -> src/Resources/{$type}）
        $basePath = dirname(__DIR__, 1) . "/Resources/{$type}";
        $path = "{$basePath}/{$file}";

        // 安全检查：防止目录遍历攻击
        $realBasePath = realpath($basePath);
        $realPath = realpath($path);

        // 路径不在允许范围内、或文件不存在，返回 404
        if (!$realPath || !$realBasePath || !str_starts_with($realPath, $realBasePath)
            || !is_file($path) || !is_readable($path)
        ) {
            abort(404, "资源文件未找到: {$file}");
        }

        // MIME 类型映射表
        $mimeTypes = [
            'css'   => 'text/css',
            'js'    => 'application/javascript',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'eot'   => 'application/vnd.ms-fontobject',
        ];

        // 获取文件扩展名并设置 Content-Type
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $contentType = $mimeTypes[$extension] ?? 'application/octet-stream';

        // 设置缓存相关的响应头
        $headers = [
            'Content-Type'  => $contentType,
            'Cache-Control' => 'public, max-age=31536000', // 1年缓存（31,536,000秒）
            'Expires'       => gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000),
        ];

        // 添加 ETag 用于客户端缓存验证
        $etag = md5($path . filemtime($path));
        $headers['ETag'] = $etag;

        // 检查客户端是否已有缓存的版本（304 Not Modified）
        $clientEtag = request()->header('If-None-Match');
        if ($clientEtag && $clientEtag === $etag) {
            return new Response('', 304, $headers);
        }

        // 返回文件内容
        return response()->file($path, $headers);
    }

    /**
     * 设置响应缓存头
     *
     * 缓存策略：
     * - Cache-Control: public, max-age=31536000（1年）
     * - Expires: 1年后的日期
     *
     * @param  Response  $response  Laravel 响应对象
     *
     * @return Response
     */
    protected function cacheResponse(Response $response): Response
    {
        // 设置共享最大缓存时间（1年 = 31,536,000秒）
        $response->setSharedMaxAge(31536000);
        // 设置私有最大缓存时间
        $response->setMaxAge(31536000);
        // 设置过期时间为1年后
        $response->setExpires(new DateTime('+1 year'));

        return $response;
    }
}
