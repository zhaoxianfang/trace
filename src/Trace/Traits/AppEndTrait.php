<?php

namespace zxf\Trace\Traits;

use Illuminate\Support\Facades\Response;

/**
 * 应用结束时的处理 Trait
 *
 * 负责：
 * 1. 注册 shutdown 函数，处理被 die/exit 终止的情况
 * 2. 调用自定义的 Trace 结束处理回调
 */
trait AppEndTrait
{
    /**
     * 注册应用被 die、exit 终止时的处理函数
     *
     * @param  \Illuminate\Http\Request  $request  请求对象
     */
    public function registerShutdownHandle($request): void
    {
        // 使用请求级别的静态标记，防止同一请求中多次注册
        $requestId = spl_object_id($request);
        static $registeredRequests = [];

        // 检查是否已经注册过此请求的 shutdown 函数
        if (isset($registeredRequests[$requestId])) {
            return;
        }

        $registeredRequests[$requestId] = true;

        // 注册 shutdown 函数，在脚本结束时执行
        register_shutdown_function(function () use ($request) {
            // 静态标记，防止同一请求多次处理响应
            static $responseProcessed = false;
            if ($responseProcessed) {
                return;
            }

            // 标记响应已处理（提前标记防止递归）
            $responseProcessed = true;

            // 检查是否启用 trace
            if (! is_enable_trace()) {
                return;
            }

            // 捕获并获取输出缓冲区的内容
            $output = ob_get_clean();

            // 检查输出是否为空
            if (empty($output)) {
                return;
            }

            try {
                // 创建 Laravel 的 Response 对象
                $response = Response::make($output, 200);

                /** @var \zxf\Trace\Handle $trace */
                $trace = app('trace');

                // 只在 trace 服务可用时执行
                if ($trace) {
                    $resp = $trace->renderTraceStyleAndScript($request, $response);
                    $output = $resp->getContent();
                }

                // 输出响应内容到浏览器
                echo $output;
            } catch (\Throwable $e) {
                // 发生异常时，输出原始内容
                echo $output;
                if (config('app.debug', false)) {
                    error_log('[Trace] Shutdown handler error: ' . $e->getMessage());
                }
            }
        });
    }

    /**
     * Trace 调试结束时的处理
     *
     * 调用自定义处理类来处理 Trace 收集的数据
     *
     * @param  array  $traceData  Trace 调试产生的所有数据
     */
    public function traceEndHandle(array $traceData = []): void
    {
        try {
            // 获取自定义处理类配置
            $handleClass = config('trace.end_handle_class');

            // 如果未配置，直接返回
            if (empty($handleClass) || ! is_string($handleClass)) {
                return;
            }

            // 检查自定义处理类是否存在
            if (! class_exists($handleClass)) {
                if (config('app.debug', false)) {
                    error_log("[Trace] Custom end handle class not found: {$handleClass}");
                }
                return;
            }

            // 检查自定义处理类中是否存在 handle 方法
            if (! method_exists($handleClass, 'handle')) {
                if (config('app.debug', false)) {
                    error_log("[Trace] Custom end handle class missing 'handle' method: {$handleClass}");
                }
                return;
            }

            // 实例化自定义处理类
            $callClass = new $handleClass();

            // 检查 handle 方法是否可调用
            if (! is_callable([$callClass, 'handle'])) {
                return;
            }

            // 调用自定义处理类的 handle 方法，传递 Trace 数据
            $callClass->handle($traceData);
        } catch (\Throwable $e) {
            // 静默处理异常，避免影响主流程
            if (config('app.debug', false)) {
                error_log('[Trace] Error in traceEndHandle: ' . $e->getMessage());
            }
        }
    }
}
