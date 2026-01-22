<?php

namespace zxf\Trace\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use zxf\Trace\Handle;
use zxf\Trace\TraceExceptionHandler;

/**
 * Trace 调试中间件
 *
 * 负责：
 * 1. 拦截所有 HTTP 请求和响应
 * 2. 为需要处理的响应注入 Trace 调试信息
 * 3. 防止重复处理同一请求
 */
class TraceMiddleware
{
    /**
     * @var Handle Trace 处理器实例
     */
    protected $handle;

    /**
     * 处理 HTTP 请求
     *
     * @param  Request  $request  HTTP 请求对象
     * @param  Closure  $next      下一个中间件闭包
     *
     * @return Response|JsonResponse|BinaryFileResponse 返回支持的响应类型
     */
    public function handle(Request $request, Closure $next)
    {
        // 使用请求对象 ID 检查是否已经处理过此请求
        $requestId = spl_object_id($request);
        static $processedRequests = [];

        // 如果已处理过，直接返回响应，避免重复处理
        if (isset($processedRequests[$requestId])) {
            return $next($request);
        }

        // 获取 Trace 处理器实例
        $this->handle = app('trace');

        // 注册 shutdown 处理函数（处理 die/exit 的情况）
        $this->handle->registerShutdownHandle($request);

        // 执行下一个中间件并获取响应
        $response = $next($request);

        // 标记此请求已处理
        $processedRequests[$requestId] = true;

        // 检查响应是否需要 Trace 处理
        if (! $this->shouldHandleResponse($response, $request)) {
            return $response;
        }

        // 在响应发送到浏览器前处理 Trace 内容
        try {
            return $this->handle->renderTraceStyleAndScript($request, $response);
        } catch (\Exception) {
            // 如果 Trace 处理失败，返回原始响应（静默处理）
            return $response;
        }
    }

    /**
     * 判断响应是否需要 Trace 处理
     *
     * 排除不需要处理的响应类型：
     * - 文件下载响应
     * - JSON 响应（通常不需要注入 HTML）
     * - Trace 未启用的情况
     * - 可选：AJAX 请求（根据需求配置）
     *
     * @param  SymfonyResponse  $response  HTTP 响应对象（支持多种响应类型）
     * @param  Request  $request  HTTP 请求对象
     *
     * @return bool true 表示需要处理，false 表示不需要处理
     */
    protected function shouldHandleResponse(SymfonyResponse $response, Request $request): bool
    {
        // 检查是否为文件下载响应（BinaryFileResponse）
        if ($response instanceof BinaryFileResponse) {
            return false;
        }

        // 检查是否为 JSON 响应（JsonResponse）
        // JSON 响应不需要注入 HTML 内容，直接返回 false
        if ($response instanceof JsonResponse) {
            return false;
        }

        // 检查是否为 AJAX 请求或期望 JSON 的请求
        // 这类请求通常不需要 HTML 注入
        if ($request->ajax() || $request->expectsJson()) {
            return false;
        }

        // 检查是否启用了 Trace 调试功能
        if (! is_enable_trace()) {
            return false;
        }

        // 只处理 HTML 内容类型的响应
        $contentType = $response->headers->get('Content-Type', '');
        if (! str_contains(strtolower($contentType), 'text/html')) {
            return false;
        }

        // 默认处理所有符合条件的响应
        return true;
    }

    /**
     * 在响应发送到浏览器后执行的清理任务
     *
     * 注意：
     * 1. 此方法在响应发送到客户端后执行
     * 2. 测试发现此方法有时不会执行，因此不能在此做各种"输出"操作
     * 3. 仅适合做一些清理工作或记录日志
     * 4. 此处清理请求级别的数据，避免内存累积
     *
     * @param  Request  $request  HTTP 请求对象
     * @param  Response  $response  HTTP 响应对象
     *
     * @return Response
     */
    public function terminate($request, $response)
    {
        try {
            // 获取请求 ID
            $requestId = spl_object_id($request);

            // 通过反射获取 Handle 实例的 requestId
            if (app()->bound('trace')) {
                $trace = app('trace');
                $reflectionClass = new \ReflectionClass($trace);
                if ($reflectionClass->hasProperty('requestId')) {
                    $requestIdProperty = $reflectionClass->getProperty('requestId');
                    $requestIdProperty->setAccessible(true);
                    $traceRequestId = $requestIdProperty->getValue($trace);

                    // 清理 TraceExceptionHandler 中的请求级别异常哈希
                    TraceExceptionHandler::clearRequestExceptions($traceRequestId);
                }
            }

        } catch (\Exception $e) {
            // 静默处理异常，避免影响主流程
        }

        return $response;
    }
}
