<?php

namespace zxf\Trace;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Request;
use ParseError;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Trace 异常处理器
 *
 * 负责全局异常捕捉和处理，包括：
 * 1. 记录异常日志
 * 2. 渲染异常响应
 * 3. 支持自定义回调处理
 * 4. 防止重复报告
 */
class TraceExceptionHandler implements ExceptionHandler
{
    protected ExceptionHandler $handler;

    protected ?Throwable $lastException = null;

    protected bool $rendering = false;

    protected int $maxReportedExceptions = 100;

    protected array $reportedHashes = [];

    protected Handle $trace;

    protected static array $requestExceptionHashes = [];

    /**
     * 标记是否已经注册全局异常监听
     */
    private static bool $globalListenerRegistered = false;

    public function __construct(ExceptionHandler $handler)
    {
        $this->handler = $handler;
        $this->initTrace();
    }

    /**
     * 初始化 Trace 实例
     *
     * @return void
     */
    protected function initTrace(): void
    {
        try {
            if (app()->bound('trace')) {
                $this->trace = app('trace');
            } else {
                // 如果 trace 服务不可用，创建一个最小实例
                $this->trace = new Handle(app());
            }
        } catch (\Throwable $e) {
            // 创建最小化的 Handle 实例作为回退
            $this->trace = new class(app()) extends Handle {
                public function __construct($app)
                {
                    // 不调用父类构造函数，避免依赖问题
                }
            };
        }
    }

    /**
     * 报告异常（增强异常报告逻辑）：负责记录异常（后台操作）
     *
     * 注意：使用请求级别的哈希跟踪，防止同一异常在单次请求中被多次报告
     */
    public function report(Throwable $e): void
    {
        try {
            // 生成请求级别的异常哈希（包含请求 ID）
            $requestExceptionHash = $this->getRequestExceptionHash($e);

            // 检查此异常是否已经在当前请求中报告过
            if (isset(self::$requestExceptionHashes[$requestExceptionHash])) {
                return;
            }

            // 初始化错误信息
            try {
                $this->trace->initError($e);
            } catch (\Throwable $initError) {
                error_log('[Trace] Failed to initialize error: '.$initError->getMessage());
            }

            $exceptionHash = $this->getExceptionHash($e);

            // 定义为不需要被报告的异常 || 检查是否已经报告过
            if ($this->shouldntReport($e) || $this->hasReported($exceptionHash)) {
                return;
            }

            // 防止内存泄漏，限制存储的异常数量
            $this->cleanupReportedExceptions();

            // 标记为已报告
            $this->reportedHashes[$exceptionHash] = microtime(true);
            self::$requestExceptionHashes[$requestExceptionHash] = true;
            $this->lastException = $e;

            // 执行报告流程
            $this->executeReport($e);

        } catch (Throwable $criticalError) {
            error_log('[Trace] Critical error in report(): '.$criticalError->getMessage());
        }
    }

    /**
     * 执行异常报告流程
     *
     * @param Throwable $e
     * @return void
     */
    protected function executeReport(Throwable $e): void
    {
        try {
            // 执行跟踪相关的预处理
            $this->beforeReport($e);

            // 记录日志
            $this->performLogWrite($e);

            // 调用原始 report 方法
            if (method_exists($this->handler, 'report')) {
                $this->handler->report($e);
            }

            // 执行报告后的处理
            $this->afterReport($e);

        } catch (Throwable $reportError) {
            $this->handleReportError($reportError);
        }
    }

    /**
     * 执行日志写入
     *
     * @param Throwable $e
     * @return void
     */
    protected function performLogWrite(Throwable $e): void
    {
        // 检查是否已经记录过
        $logAlreadyRecorded = false;
        try {
            if (app()->bound('request') && request()) {
                $logAlreadyRecorded = request()->has('log_already_recorded');
            }
        } catch (\Throwable) {
            // 静默处理
        }

        if (! $logAlreadyRecorded) {
            $this->trace->writeLog($e);
        }
    }

    /**
     * 处理报告过程中的错误
     *
     * @param Throwable $reportError
     * @return void
     */
    protected function handleReportError(Throwable $reportError): void
    {
        try {
            $this->trace->writeLog($reportError);
        } catch (\Throwable) {
            error_log('[Trace] Exception in reporting: '.$reportError->getMessage());
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

        try {
            $response = $this->doRender($request, $e);
            $this->rendering = false;
            return $response;
        } catch (Throwable $renderError) {
            $this->rendering = false;
            if (config('app.debug', false)) {
                error_log('[Trace] Render error: ' . $renderError->getMessage());
            }
            return $this->handler->render($request, $e);
        }
    }

    /**
     * 执行实际的渲染逻辑
     *
     * @param mixed $request
     * @param Throwable $e
     * @return Response
     */
    protected function doRender($request, Throwable $e): Response
    {
        // 初始化错误信息（如果尚未初始化）
        if (! $this->trace::$initErr) {
            $this->trace->initError($e);
        }

        // 1. 尝试自定义回调处理
        $callbackResponse = $this->tryCustomCallback($e);
        if ($callbackResponse !== null) {
            return $callbackResponse;
        }

        // 2. 尝试模块自定义异常处理
        $moduleResponse = $this->tryModuleHandler($e, $request);
        if ($moduleResponse !== null) {
            return $moduleResponse;
        }

        // 3. 根据请求类型和调试模式渲染响应
        return $this->renderByContext($request, $e);
    }

    /**
     * 尝试自定义回调处理
     *
     * @param Throwable $e
     * @return Response|null
     */
    protected function tryCustomCallback(Throwable $e): ?Response
    {
        try {
            $result = $this->trace->runCallbackHandle($e);
            if ($result instanceof Response) {
                return $result;
            }
        } catch (Throwable $err) {
            if (config('app.debug', false)) {
                error_log('[Trace] Callback error: ' . $err->getMessage());
            }
        }
        return null;
    }

    /**
     * 尝试模块自定义异常处理
     *
     * @param Throwable $e
     * @param mixed $request
     * @return Response|null
     */
    protected function tryModuleHandler(Throwable $e, $request): ?Response
    {
        try {
            if ($this->trace->hasModuleCustomException()) {
                $response = $this->trace->handleModulesCustomException($e, $request);
                if ($response instanceof Response) {
                    return $response;
                }
            }
        } catch (Throwable $err) {
            if (config('app.debug', false)) {
                error_log('[Trace] Module handler error: ' . $err->getMessage());
            }
        }
        return null;
    }

    /**
     * 根据上下文渲染响应
     *
     * @param mixed $request
     * @param Throwable $e
     * @return Response
     */
    protected function renderByContext($request, Throwable $e): Response
    {
        $isAjaxRequest = $request->is('api/*') || ! $request->isMethod('get') || $request->expectsJson();
        $isDebug = config('app.debug') || app()->runningInConsole();

        // 调试模式下返回详细错误信息
        if ($isDebug && ! $isAjaxRequest) {
            return $this->trace->debug($e);
        }

        // 生产环境或 AJAX 请求返回友好错误
        if ($isAjaxRequest) {
            return $this->trace->respJson($this->trace::$message, $this->trace::$code);
        }

        return $this->trace->respView($this->trace::$message, $this->trace::$code);
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
        $requestId = '';

        // 安全地获取 requestId - 使用公共方法优先，避免反射
        try {
            if (method_exists($this->trace, 'getRequestId')) {
                $requestId = $this->trace->getRequestId() ?? '';
            }
        } catch (\Throwable) {
            // 失败时，使用备选方案
            $requestId = (string) time();
        }

        return md5(
            get_class($e).
            $e->getFile().
            $e->getLine().
            $requestId
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
