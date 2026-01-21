<?php

namespace zxf\Trace;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Request;
use ParseError;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * 处理异常错误
 */
class TraceExceptionHandler implements ExceptionHandler
{
    protected ExceptionHandler $handler;

    protected ?Throwable $lastException = null;

    protected bool $rendering = false;

    protected int $maxReportedExceptions = 100; // 防止内存泄漏

    // 添加异常跟踪数组，用于去重
    protected array $reportedHashes = [];

    protected Handle $trace;

    // 请求级别的异常处理标记，防止同一异常在单次请求中被多次处理
    protected static array $requestExceptionHashes = [];

    public function __construct(ExceptionHandler $handler)
    {
        $this->handler = $handler;

        $this->trace = app('trace');
    }

    /**
     * 报告异常（增强异常报告逻辑）：负责记录异常（后台操作）
     *
     * 注意：使用请求级别的哈希跟踪，防止同一异常在单次请求中被多次报告
     */
    public function report(Throwable $e): void
    {
        // 生成请求级别的异常哈希（包含请求 ID）
        $requestExceptionHash = $this->getRequestExceptionHash($e);

        // 检查此异常是否已经在当前请求中报告过
        if (isset(self::$requestExceptionHashes[$requestExceptionHash])) {
            return;
        }

        // 初始化错误信息
        $this->trace->initError($e);

        $exceptionHash = $this->getExceptionHash($e);

        // 定义为不需要被报告的异常 || 检查是否已经报告过
        if ($this->shouldntReport($e) || $this->hasReported($exceptionHash)) {
            return;
        }

        // 防止内存泄漏，限制存储的异常数量
        $this->cleanupReportedExceptions();

        // 标记为已报告（全局级别）
        $this->reportedHashes[$exceptionHash] = microtime(true);

        // 标记为已在当前请求中报告（请求级别）
        self::$requestExceptionHashes[$requestExceptionHash] = true;

        $this->lastException = $e;

        try {
            // 执行跟踪相关的预处理
            $this->beforeReport($e);

            // 记录日志（检查是否已经记录过，防止重复记录）
            // 注意：检查 request 是否可用，避免在非 HTTP 环境下出错
            $logAlreadyRecorded = false;
            try {
                if (app()->bound('request') && request()) {
                    $logAlreadyRecorded = request()->has('log_already_recorded');
                }
            } catch (\Throwable) {
                // 静默处理，使用默认值
            }

            if (! $logAlreadyRecorded) {
                $this->trace->writeLog($e);
            }

            // 调用原始 report 方法
            $this->handler->report($e);

            // 执行报告后的处理
            $this->afterReport($e);

        } catch (Throwable $reportError) {
            // 避免报告过程中的异常导致无限循环
            // 记录日志
            $this->trace->writeLog($reportError);
        }
    }

    /**
     * 增强异常渲染逻辑
     */
    public function render($request, Throwable $e): Response
    {
        // 防止递归调用
        if ($this->rendering) {
            return $this->handler->render($request, $e);
        }

        $this->rendering = true;
        $this->lastException = $e;

        if (! $this->trace::$initErr) {
            // 可能部分异常不会走 report，例如：abort(401,'...');
            // 手动重新调用  report报告
            $this->trace->initError($e);
        }

        // 运行自定义闭包回调
        try {
            $callRes = $this->trace->runCallbackHandle($e);
            if (! empty($callRes) && $callRes instanceof Response) {
                $this->rendering = false;
                return $callRes;
            }
        } catch (Throwable $err) {
            // 忽略回调中的错误
        }

        try {
            // 如果模块下定义了自定义的异常接管类 Handler，则交由模块下的异常类自己处理
            if ($this->trace->hasModuleCustomException()) {
                $moduleResponse = $this->trace->handleModulesCustomException($e, $request);
                if ($moduleResponse) {
                    $this->rendering = false;
                    return $moduleResponse;
                }
            }
        } catch (Throwable $err) {
            // 可能自定义接管的异常类也有异常，忽略并继续处理
        }

        // 调试模式
        if (config('app.debug') || app()->runningInConsole()) {
            try {
                $response = $this->trace->debug($e);
                $this->rendering = false;
                return $response;
            } catch (Throwable $err) {
                // 如果调试渲染失败，使用默认渲染
                $this->rendering = false;
                return $this->handler->render($request, $e);
            }
        }

        // 判断路径 : 不是get的api 或 json 请求
        try {
            if (($request->is('api/*') || ! $request->isMethod('get')) || $request->expectsJson()) {
                $response = $this->trace->respJson($this->trace::$message, $this->trace::$code)->send();
            } else {
                $response = $this->trace->respView($this->trace::$message, $this->trace::$code)->send();
            }
            $this->rendering = false;
            return $response;
        } catch (Throwable $err) {
            // 如果自定义渲染失败，使用默认渲染
            $this->rendering = false;
            return $this->handler->render($request, $e);
        }
    }

    public function shouldReport(Throwable $e): bool
    {
        return $this->handler->shouldReport($e);
    }

    public function renderForConsole($output, Throwable $e): void
    {
        $this->handler->renderForConsole($output, $e);
    }

    /**
     * 检查异常是否在不报告列表中
     */
    protected function shouldntReport(Throwable $e): bool
    {
        foreach ($this->trace->dontReport as $type) {
            if ($e instanceof $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * 报告前的预处理
     */
    protected function beforeReport(Throwable $e): void
    {
        try {
            // 不是生产环境，且已绑定了 trace 组件，则注册 shutdown 钩子
            // if (app()->bound('trace') && Request::hasMacro('instance') && ! app()->isProduction()) {
            if (app()->bound('trace') && Request::hasMacro('instance')) {
                $this->trace->registerShutdownHandle(Request::instance());
            }
        } catch (Throwable $traceError) {
            // 静默处理跟踪错误，不影响主要异常报告流程
            // 记录日志
            $this->trace->writeLog($traceError);
        }
    }

    /**
     * 报告后的处理
     */
    protected function afterReport(Throwable $e): void
    {
        // 可以在这里添加报告后的额外处理逻辑
    }

    /**
     * 处理跟踪信息
     */
    protected function pringTrace($request, Response $response): Response
    {
        if ($this->lastException instanceof ParseError) {
            set_protected_attr($response, 'exception', $this->lastException);

            return $this->trace->renderTraceStyleAndScript($request, $response);
        }

        return $response;
    }

    /**
     * 生成异常的唯一哈希（全局级别）
     *
     * 用于跨请求的去重检查
     */
    protected function getExceptionHash(Throwable $e): string
    {
        return md5(
            get_class($e).
            $e->getFile().
            $e->getLine().
            $this->trace::$message.
            $this->trace::$code
        );
    }

    /**
     * 生成请求级别的异常哈希
     *
     * 包含请求 ID，确保同一异常在单次请求中只被处理一次
     *
     * @param  Throwable  $e  异常对象
     * @return string 请求级别的哈希值
     */
    protected function getRequestExceptionHash(Throwable $e): string
    {
        // 通过反射获取 Handle 的 requestId 属性
        $reflectionClass = new \ReflectionClass($this->trace);
        $requestIdProperty = $reflectionClass->getProperty('requestId');
        $requestIdProperty->setAccessible(true);
        $requestId = $requestIdProperty->getValue($this->trace);

        return md5(
            get_class($e).
            $e->getFile().
            $e->getLine().
            $requestId  // 包含请求 ID，确保请求级别的去重
        );
    }

    /**
     * 检查异常是否已经报告过
     */
    protected function hasReported(string $exceptionHash): bool
    {
        return isset($this->reportedHashes[$exceptionHash]);
    }

    /**
     * 清理已报告的异常记录，防止内存泄漏
     */
    protected function cleanupReportedExceptions(): void
    {
        if (count($this->reportedHashes) > $this->maxReportedExceptions) {
            // 保留最近的一半记录
            $half = (int) ($this->maxReportedExceptions / 2);
            $this->reportedHashes = array_slice(
                $this->reportedHashes,
                -$half,
                $half,
                true
            );
        }

        // 可选：清理超过一定时间的记录（例如1小时）
        $oneHourAgo = microtime(true) - 3600;
        $this->reportedHashes = array_filter(
            $this->reportedHashes,
            fn ($timestamp) => $timestamp > $oneHourAgo
        );
    }

    /**
     * 获取已报告异常的数量（用于监控）
     */
    public function getReportedCount(): int
    {
        return count($this->reportedHashes);
    }

    /**
     * 清空已报告的异常记录
     */
    public function clearReportedExceptions(): void
    {
        $this->reportedHashes = [];
    }

    /**
     * 析构函数 - 清理资源
     */
    public function __destruct()
    {
        // 清空大数组，帮助GC
        $this->reportedHashes = [];
        $this->lastException = null;

        // 清理请求级别的异常哈希（当请求结束时）
        // 注意：在 Laravel 11+ 中，可以使用 Request::macro() 或其他机制来清理
        // 这里提供基础清理，更完善的清理需要结合中间件或请求生命周期钩子
        self::$requestExceptionHashes = [];
    }

    /**
     * 清理请求级别的异常哈希
     *
     * 此方法应在请求结束时调用（例如在中间件的 terminate 方法中）
     * 以清理当前请求的异常记录，避免内存累积
     *
     * @param  string  $requestId  请求 ID
     */
    public static function clearRequestExceptions(string $requestId): void
    {
        // 清理包含特定请求 ID 的所有异常哈希
        self::$requestExceptionHashes = array_filter(
            self::$requestExceptionHashes,
            function ($hash) use ($requestId) {
                // 保留不包含当前请求 ID 的哈希
                return ! str_contains($hash, $requestId);
            }
        );
    }
}
