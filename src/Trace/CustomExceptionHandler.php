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
 * 使用示例：
 * ->withExceptions(function (Exceptions $exceptions): void {
 *     \zxf\Trace\CustomExceptionHandler::handle($exceptions, function ($code, $message, $exception) {
 *         if ($code == 401) {
 *             return redirect()->route('login');
 *         }
 *         return null;
 *     }, [401]);
 * })
 */
class CustomExceptionHandler
{
    /**
     * 标记是否已初始化，防止重复初始化
     */
    private static bool $initialized = false;

    /**
     * 存储的自定义回调
     */
    private static ?Closure $storedCallback = null;

    /**
     * 存储的自定义处理错误码
     */
    private static array $storedCustomHandleCode = [];

    /**
     * 存储的不报告异常列表
     */
    private static array $storedDontReport = [];

    /**
     * 初始化 Laravel 11+ 异常处理配置
     *
     * 功能说明：
     * 1. 设置自定义异常回调函数（例如处理 401 时重定向到登录页）
     * 2. 配置不需要报告的异常列表
     * 3. 确保异常只被报告一次（通过 dontReportDuplicates）
     * 4. 防止重复初始化
     *
     * @param  Exceptions  $exceptions  Laravel 11+ 异常配置对象
     * @param  Closure|null  $customHandleCallback  自定义处理回调，参数：($code, $message, $exception)
     * @param  array  $customHandleCode  需要执行回调的错误码列表，为空表示所有错误码都触发回调
     * @param  array  $dontReport  不需要被报告的异常类列表
     */
    public static function handle(Exceptions $exceptions, ?Closure $customHandleCallback = null, array $customHandleCode = [], array $dontReport = []): void
    {
        // 防止重复初始化
        if (self::$initialized) {
            // 如果已经初始化，只更新回调配置
            self::updateCallbackConfig($customHandleCallback, $customHandleCode, $dontReport);
            return;
        }

        // 记录配置
        self::$storedCallback = $customHandleCallback;
        self::$storedCustomHandleCode = $customHandleCode;
        self::$storedDontReport = $dontReport;

        // 检查 trace 服务是否已定义
        if (! app()->bound('trace')) {
            // 如果 trace 服务未绑定，延迟注册
            self::registerDeferredHandler($exceptions);
            return;
        }

        // 执行初始化
        self::performInitialization($exceptions);
    }

    /**
     * 执行实际初始化
     *
     * @param  Exceptions  $exceptions
     * @return void
     */
    private static function performInitialization(Exceptions $exceptions): void
    {
        /** @var Handle $trace */
        $trace = app('trace');

        // 去重复报告的异常，确保单个实例的异常只被报告一次
        $exceptions->dontReportDuplicates();

        // 注册渲染回调
        $exceptions->render(function ($request, \Throwable $e) use ($trace) {
            return self::handleExceptionRender($request, $e, $trace);
        });

        // 注册报告回调
        $exceptions->report(function (\Throwable $e) use ($trace) {
            return self::handleExceptionReport($e, $trace);
        });

        // 设置自定义回调
        if (self::$storedCallback !== null) {
            $trace->setCustomCallbackHandel(self::$storedCallback, self::$storedCustomHandleCode);
        }

        // 设置不需要报告的异常
        if (! empty(self::$storedDontReport)) {
            $trace->setDontReport(self::$storedDontReport);
        }

        // 标记为已初始化
        self::$initialized = true;
    }

    /**
     * 延迟注册处理器（当 trace 服务尚未绑定时）
     *
     * @param  Exceptions  $exceptions
     * @return void
     */
    private static function registerDeferredHandler(Exceptions $exceptions): void
    {
        // 使用容器解析事件监听，等待服务就绪
        $exceptions->render(function ($request, \Throwable $e) {
            if (app()->bound('trace')) {
                /** @var Handle $trace */
                $trace = app('trace');
                return self::handleExceptionRender($request, $e, $trace);
            }
            return null;
        });
    }

    /**
     * 更新回调配置（在重复调用时）
     *
     * @param  Closure|null  $customHandleCallback
     * @param  array  $customHandleCode
     * @param  array  $dontReport
     * @return void
     */
    private static function updateCallbackConfig(?Closure $customHandleCallback, array $customHandleCode, array $dontReport): void
    {
        if (! app()->bound('trace')) {
            return;
        }

        /** @var Handle $trace */
        $trace = app('trace');

        // 更新存储的配置
        if ($customHandleCallback !== null) {
            self::$storedCallback = $customHandleCallback;
            $trace->setCustomCallbackHandel($customHandleCallback, $customHandleCode);
        }

        if (! empty($dontReport)) {
            self::$storedDontReport = array_merge(self::$storedDontReport, $dontReport);
            $trace->setDontReport(self::$storedDontReport);
        }
    }

    /**
     * 处理异常渲染
     *
     * @param  mixed  $request
     * @param  \Throwable  $e
     * @param  Handle  $trace
     * @return mixed
     */
    private static function handleExceptionRender($request, \Throwable $e, Handle $trace)
    {
        try {
            // 初始化错误信息
            if (! $trace::$initErr) {
                $trace->initError($e);
            }

            // 运行自定义闭包回调
            $callbackResult = $trace->runCallbackHandle($e);
            if (! empty($callbackResult)) {
                return $callbackResult;
            }

            // 检查模块自定义异常处理
            if ($trace->hasModuleCustomException()) {
                $moduleResponse = $trace->handleModulesCustomException($e, $request);
                if ($moduleResponse) {
                    return $moduleResponse;
                }
            }

            // 判断是否 AJAX 请求
            $isAjaxRequest = $request->is('api/*') || ! $request->isMethod('get') || $request->expectsJson();

            // 调试模式下返回详细错误信息
            if (config('app.debug') && ! $isAjaxRequest) {
                return $trace->debug($e);
            }

            // 非调试模式下返回友好错误响应
            if ($isAjaxRequest) {
                return $trace->respJson($trace::$message, $trace::$code);
            }

            return $trace->respView($trace::$message, $trace::$code);

        } catch (\Throwable $err) {
            // 处理失败时记录日志但不中断流程
            if (config('app.debug', false)) {
                error_log('[Trace] Exception render error: ' . $err->getMessage());
            }
            return null;
        }
    }

    /**
     * 处理异常报告
     *
     * @param  \Throwable  $e
     * @param  Handle  $trace
     * @return bool
     */
    private static function handleExceptionReport(\Throwable $e, Handle $trace): bool
    {
        try {
            // 初始化错误信息
            if (! $trace::$initErr) {
                $trace->initError($e);
            }

            // 写入日志
            $trace->writeLog($e);

            // 返回 false 表示继续执行其他报告处理
            return false;

        } catch (\Throwable $err) {
            if (config('app.debug', false)) {
                error_log('[Trace] Exception report error: ' . $err->getMessage());
            }
            return false;
        }
    }

    /**
     * 重置初始化状态（用于测试）
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$initialized = false;
        self::$storedCallback = null;
        self::$storedCustomHandleCode = [];
        self::$storedDontReport = [];
    }

    /**
     * 检查是否已初始化
     *
     * @return bool
     */
    public static function isInitialized(): bool
    {
        return self::$initialized;
    }
}
