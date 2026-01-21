<?php

namespace zxf\Trace;

use Closure;
use Illuminate\Foundation\Configuration\Exceptions;

/**
 * Laravel 11+ 自定义异常处理配置类
 *
 * 用途：
 * 在 bootstrap/app.php 的 withExceptions() 中调用，用于配置自定义异常处理逻辑
 *
 * 注意事项：
 * 1. 此类不会创建新的异常处理器，仅用于配置 Trace 包的异常处理行为
 * 2. TraceExceptionHandler 已经通过 ServiceProvider 接管了异常处理
 * 3. 此类的作用是设置自定义回调和不需要报告的异常列表
 * 4. 不会导致重复处理，因为只是配置而非创建新的处理器
 */
class CustomExceptionHandler
{
    /**
     * 初始化 Laravel 11+ 异常处理配置
     *
     * 功能说明：
     * 1. 设置自定义异常回调函数（例如处理 401 时重定向到登录页）
     * 2. 配置不需要报告的异常列表
     * 3. 确保异常只被报告一次（通过 dontReportDuplicates）
     *
     * 工作流程：
     * bootstrap/app.php 中调用此方法 → 设置回调 → 异常发生时 TraceExceptionHandler::report() 调用 → 执行自定义回调
     *
     * @param  Exceptions  $exceptions  Laravel 11+ 异常配置对象
     * @param  Closure|null  $customHandleCallback  自定义处理回调，参数：($code, $message, $exception)
     * @param  array  $customHandleCode  需要执行回调的错误码列表，为空表示所有错误码都触发回调
     * @param  array  $dontReport  不需要被报告的异常类列表
     */
    public static function handle(Exceptions $exceptions, ?Closure $customHandleCallback = null, array $customHandleCode = [], array $dontReport = []): void
    {
        // 检查 trace 服务是否已定义
        if (! app()->bound('trace')) {
            return;
        }

        /** @var Handle $trace */
        $trace = app('trace');

        // 去重复报告的异常,确保单个实例的异常只被报告一次
        // 注意：此方法调用 Laravel 内置的去重逻辑，与我们的请求级别去重配合使用
        $exceptions->dontReportDuplicates();

        // 自定义状态码的异常闭包回调处理
        // 使用示例：
        // CustomExceptionHandler::handle($exceptions, function ($code, $message, $exception) {
        //     if ($code == 401) {
        //         return to_module_login();
        //     }
        // });
        if ($customHandleCallback !== null) {
            $trace->setCustomCallbackHandel($customHandleCallback, $customHandleCode);
        }

        // 定义不需要被报告的异常类
        // 使用示例：
        // CustomExceptionHandler::handle($exceptions, null, [], [
        //     \Illuminate\Auth\AuthenticationException::class,
        // ]);
        if (! empty($dontReport)) {
            $trace->setDontReport($dontReport);
        }
    }
}
