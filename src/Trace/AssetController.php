<?php

namespace zxf\Trace;

use DateTime;
use Illuminate\Http\Response;

/**
 * 资源文件控制器
 *
 * 负责：
 * 1. 提供 CSS 资源文件的访问
 * 2. 提供 JavaScript 资源文件的访问
 * 3. 实现浏览器缓存机制
 */
class AssetController
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
            dirname(__DIR__, 1).'/Resources/js/trace.js',
        ];
        // 定义 CSS 文件路径
        $this->cssFiles = [
            dirname(__DIR__, 1).'/Resources/css/trace.css',
        ];
    }

    /**
     * 获取 JavaScript 调试文件
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
                        $content .= $fileContent."\n";
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
     * 获取 CSS 调试文件
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
                        $content .= $fileContent."\n";
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
