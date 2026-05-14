<?php

/**
 * Trace 包早期引导文件
 *
 * 在 Laravel 框架启动前通过 composer autoload.files 加载，
 * 确保 register_shutdown_function 在 Laravel 之前抢先注册。
 *
 * 拦截范围：
 * - PHP 致命错误 (E_ERROR): 内存耗尽、无限递归等
 * - 未捕获异常
 * - 内存相关警告
 */

namespace TraceEarly;

final class EarlyInterceptor
{
    public static ?array $interceptedError = null;
    private static bool $registered = false;
    private static bool $isHandling = false;
    private static $originalErrorHandler = null;
    private static $originalExceptionHandler = null;
    private static string $executionId;
    private static int $memoryThreshold = 5242880; // 5MB

    public static function register(): void
    {
        if (self::$registered) return;
        self::$executionId = substr(md5(uniqid('trace_early_', true)), 0, 12);

        self::$originalErrorHandler = set_error_handler(
            [self::class, 'handleError'], E_ALL
        );
        self::$originalExceptionHandler = set_exception_handler(
            [self::class, 'handleException']
        );
        register_shutdown_function([self::class, 'handleShutdown']);

        self::$registered = true;
    }

    /**
     * 错误处理器 - 内存相关错误立即拦截，其他错误转为异常
     */
    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) return false;

        if (self::isMemoryError($message)) {
            self::storeError([
                'type' => 'MEMORY_LIMIT_WARNING',
                'code' => $severity,
                'message' => $message,
                'file' => $file,
                'line' => $line,
                'timestamp' => date('Y-m-d H:i:s'),
                'execution_id' => self::$executionId,
            ]);
            return true;
        }

        // 将错误传递给链中的下一个处理器
        if (
            self::$originalErrorHandler !== null
            && is_callable(self::$originalErrorHandler)
            && self::$originalErrorHandler !== [self::class, 'handleError']
        ) {
            try {
                return call_user_func(self::$originalErrorHandler, $severity, $message, $file, $line);
            } catch (\Throwable $e) {
                // 链式处理器可能会抛出异常
            }
        }

        // 对于可恢复错误，转为异常
        if (in_array($severity, [E_WARNING, E_USER_WARNING, E_RECOVERABLE_ERROR], true)) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }

        return false;
    }

    /**
     * 异常处理器
     */
    public static function handleException(\Throwable $exception): void
    {
        self::storeError([
            'type' => get_class($exception),
            'code' => $exception->getCode() ?: 500,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => date('Y-m-d H:i:s'),
            'execution_id' => self::$executionId,
        ]);

        // 传递给链中的下一个处理器
        if (
            self::$originalExceptionHandler !== null
            && is_callable(self::$originalExceptionHandler)
            && (!is_array(self::$originalExceptionHandler)
                || self::$originalExceptionHandler !== [self::class, 'handleException'])
        ) {
            try {
                call_user_func(self::$originalExceptionHandler, $exception);
                return;
            } catch (\Throwable $e) {
                // 原始处理器也失败了
            }
        }

        // 紧急输出
        self::emergencyOutput($exception);
    }

    /**
     * Shutdown 处理器 - 核心：捕获致命错误（内存耗尽、递归溢出等）
     */
    public static function handleShutdown(): void
    {
        if (self::$isHandling) return;

        $error = error_get_last();
        if ($error === null) return;
        if (!self::isFatalErrorType($error['type'])) return;

        self::$isHandling = true;

        try {
            // 如果还没有存储错误信息
            if (self::$interceptedError === null) {
                self::storeError([
                    'type' => self::getErrorTypeName($error['type']),
                    'code' => $error['type'],
                    'message' => $error['message'],
                    'file' => $error['file'] ?? 'unknown',
                    'line' => $error['line'] ?? 0,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'execution_id' => self::$executionId,
                    'is_fatal' => true,
                ]);
            }

            // 尝试 Blade 视图渲染
            if (!self::isLowMemory() && self::tryBladeRender()) {
                exit(1);
            }

            // 降级：紧急 HTML 输出
            $exception = new \ErrorException(
                self::$interceptedError['message'] ?? 'Unknown fatal error',
                0, $error['type'],
                $error['file'] ?? 'unknown',
                $error['line'] ?? 0
            );
            self::emergencyOutput($exception);
        } catch (\Throwable $e) {
            self::lastResortOutput(self::$interceptedError['message'] ?? $e->getMessage());
        } finally {
            self::$isHandling = false;
        }

        exit(1);
    }

    /**
     * 存储错误信息
     */
    public static function storeError(array $errorInfo): void
    {
        if (self::$interceptedError === null) {
            self::$interceptedError = $errorInfo;
        }
    }

    /**
     * 尝试使用 Blade 视图渲染
     */
    private static function tryBladeRender(): bool
    {
        if (!function_exists('view') || !function_exists('app')) return false;

        try {
            $app = app();
            if (!$app || !$app->bound('view')) return false;

            $view = $app->make('view');
            $viewPath = __DIR__ . '/Resources/views';

            if (is_dir($viewPath)) {
                try {
                    $namespaces = $view->getFinder()->getHints();
                    if (!isset($namespaces['trace'])) {
                        $view->addNamespace('trace', $viewPath);
                    }
                } catch (\Throwable $e) {
                    $view->addNamespace('trace', $viewPath);
                }
            }

            $errorInfo = self::$interceptedError;
            $isDebug = self::isDebugMode();

            $viewData = [
                'code' => 500,
                'title' => self::getErrorCategoryTitle($errorInfo ?? []),
                'message' => $errorInfo['message'] ?? '系统发生致命错误',
                'requestId' => self::$executionId,
                'timestamp' => date('Y-m-d H:i:s'),
                'isDebug' => $isDebug,
            ];

            if ($isDebug && $errorInfo !== null) {
                $viewData['list'] = [
                    ['label' => 'Error Type', 'value' => $errorInfo['type'] ?? 'Unknown', 'type' => 'string'],
                    ['label' => 'File', 'value' => ($errorInfo['file'] ?? 'unknown') . ':' . ($errorInfo['line'] ?? 0), 'type' => 'string'],
                    ['label' => 'Memory Usage', 'value' => self::getMemoryUsage(), 'type' => 'string'],
                    ['label' => 'Execution ID', 'value' => self::$executionId, 'type' => 'string'],
                ];
                $viewData['exception'] = self::createDebugException($errorInfo);
            }

            foreach (['trace::error', 'trace::debug'] as $viewName) {
                if ($view->exists($viewName)) {
                    self::cleanOutputBuffers();
                    if (!headers_sent()) {
                        http_response_code(500);
                        header('Content-Type: text/html; charset=utf-8');
                    }
                    echo $view->make($viewName, $viewData)->render();
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 创建模拟异常对象供视图使用
     */
    private static function createDebugException(array $errorInfo): object
    {
        return new class($errorInfo) extends \Exception {
            private array $info;
            public function __construct(array $info) {
                $this->info = $info;
                parent::__construct($info['message'] ?? 'Unknown', $info['code'] ?? 0);
                try {
                    $r = new \ReflectionClass(\Exception::class);
                    $fp = $r->getProperty('file'); $fp->setAccessible(true);
                    $fp->setValue($this, $info['file'] ?? 'unknown');
                    $lp = $r->getProperty('line'); $lp->setAccessible(true);
                    $lp->setValue($this, $info['line'] ?? 0);
                } catch (\Throwable $e) {}
            }
            public function getTraceStr(): string {
                return $this->info['trace'] ?? '';
            }
        };
    }

    /**
     * 紧急 HTML 输出
     */
    private static function emergencyOutput(\Throwable $exception): void
    {
        self::cleanOutputBuffers();
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        $isDebug = self::isDebugMode();
        $message = $isDebug ? $exception->getMessage() : '服务器发生内部错误，请稍后重试';
        $errorType = self::$interceptedError['type'] ?? get_class($exception);
        $errorFile = $exception->getFile() . ':' . $exception->getLine();
        $memoryUsage = self::getMemoryUsage();
        $suggestion = self::getErrorSuggestion(
            self::$interceptedError['message'] ?? $exception->getMessage(), $errorType
        );

        // 内联 HTML 模板（零依赖应急输出）
        $safeMsg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $safeType = htmlspecialchars($errorType, ENT_QUOTES, 'UTF-8');
        $safeFile = htmlspecialchars($errorFile, ENT_QUOTES, 'UTF-8');
        $safeSuggestion = htmlspecialchars($suggestion, ENT_QUOTES, 'UTF-8');
        $safeMemory = htmlspecialchars($memoryUsage, ENT_QUOTES, 'UTF-8');
        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>错误 - {$safeType}</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .card{background:rgba(255,255,255,0.05);backdrop-filter:blur(20px);border-radius:20px;padding:40px;max-width:650px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.1)}
        .header{display:flex;align-items:center;gap:16px;margin-bottom:24px}
        .icon{width:56px;height:56px;border-radius:14px;background:rgba(250,112,154,.15);display:flex;align-items:center;justify-content:center;font-size:28px}
        h1{font-size:24px;color:#fff;font-weight:600}
        .type{display:inline-block;padding:4px 12px;border-radius:6px;background:rgba(250,112,154,.15);color:#fa709a;font-size:13px;font-weight:500}
        .info{background:rgba(0,0,0,.2);border-radius:12px;padding:16px;margin-bottom:20px}
        .info-row{display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:14px;color:#a0aec0}
        .info-row:last-child{margin-bottom:0}
        .info-label{font-weight:500;color:#718096;min-width:80px}
        .message-box{background:rgba(250,112,154,.08);border:1px solid rgba(250,112,154,.2);border-radius:12px;padding:16px;margin-bottom:16px}
        .message-box p{color:#fa709a;font-size:14px;line-height:1.6;word-break:break-word}
        .suggestion-box{background:rgba(102,126,234,.08);border:1px solid rgba(102,126,234,.2);border-radius:12px;padding:16px;margin-bottom:20px}
        .suggestion-box p{color:#8b9cf7;font-size:14px;line-height:1.6}
        .footer{display:flex;justify-content:space-between;align-items:center}
        .footer a{color:#667eea;text-decoration:none;font-size:14px;padding:10px 20px;border-radius:8px;background:rgba(102,126,234,.1);transition:all .2s}
        .footer a:hover{background:rgba(102,126,234,.2)}
        .memory{font-size:12px;color:#4a5568}
        @media(max-width:600px){.card{padding:24px}.header{flex-direction:column;text-align:center}h1{font-size:20px}.footer{flex-direction:column;gap:12px}}
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div class="icon">⚠️</div>
            <div>
                <h1>发生致命错误</h1>
                <span class="type">{$safeType}</span>
            </div>
        </div>
        <div class="info">
            <div class="info-row"><span class="info-label">文件位置</span><span>{$safeFile}</span></div>
            <div class="info-row"><span class="info-label">内存状态</span><span>{$safeMemory}</span></div>
        </div>
        <div class="message-box"><p>{$safeMsg}</p></div>
        <div class="suggestion-box"><p>💡 {$safeSuggestion}</p></div>
        <div class="footer">
            <a href="/">← 返回首页</a>
            <span class="memory">Memory: {$safeMemory}</span>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 最终兜底输出
     */
    private static function lastResortOutput(string $message): void
    {
        self::cleanOutputBuffers();
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        $msg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo "<!DOCTYPE html><html lang=\"zh-CN\"><head><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>500 Error</title><style>body{font-family:system-ui,sans-serif;background:#1a1a2e;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;text-align:center}.box{background:rgba(255,255,255,0.05);padding:40px;border-radius:16px;max-width:500px}h1{color:#fa709a;font-size:48px;margin:0 0 10px}p{color:#a0aec0;margin:0 0 20px;font-size:16px}a{color:#667eea;text-decoration:none;font-size:14px}</style></head><body><div class=\"box\"><h1>500</h1><p>{$msg}</p><a href=\"/\">返回首页</a></div></body></html>";
    }

    // ========== 工具方法 ==========

    private static function isFatalErrorType(int $type): bool {
        return in_array($type, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
    }

    private static function isMemoryError(string $message): bool {
        $lower = strtolower($message);
        return str_contains($lower, 'memory') || str_contains($lower, 'allowed memory size');
    }

    private static function getErrorTypeName(int $errno): string {
        return match ($errno) {
            E_ERROR => 'FATAL_ERROR', E_WARNING => 'WARNING',
            E_PARSE => 'PARSE_ERROR', E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR', E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR', E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR', E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE', E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED', E_USER_DEPRECATED => 'USER_DEPRECATED',
            default => 'UNKNOWN_ERROR',
        };
    }

    private static function getErrorCategoryTitle(array $errorInfo): string {
        $msg = strtolower($errorInfo['message'] ?? '');
        if (str_contains($msg, 'memory') || str_contains($msg, 'allowed memory size')) return '内存耗尽错误';
        if (str_contains($msg, 'nesting') || str_contains($msg, 'recursion') || str_contains($msg, 'stack overflow')) return '递归/嵌套溢出';
        if (str_contains($msg, 'maximum execution time') || str_contains($msg, 'timeout')) return '执行超时';
        return '系统致命错误';
    }

    private static function getErrorSuggestion(string $message, string $type): string {
        $lower = strtolower($message);
        if (str_contains($lower, 'memory') || str_contains($lower, 'allowed memory size')) {
            return '系统内存耗尽。请检查深层嵌套数组、无限递归、大数据集加载或内存泄漏。建议优化数据结构，使用分页/分批处理，或增加 PHP memory_limit。';
        }
        if (str_contains($lower, 'nesting') || str_contains($lower, 'recursion') || str_contains($lower, 'stack overflow')) {
            return '函数递归深度或数据嵌套层级超过系统限制。请检查是否存在无限递归调用或循环引用。';
        }
        if (str_contains($lower, 'maximum execution time') || str_contains($lower, 'timeout')) {
            return '脚本执行时间超过限制。请检查是否存在死循环或低效算法。考虑使用队列异步处理。';
        }
        if (str_contains($lower, 'json')) {
            return 'JSON 编解码异常。请检查数据结构和编码深度。';
        }
        return '服务器发生内部错误。请检查日志获取更多信息。';
    }

    private static function isLowMemory(): bool {
        try {
            $limit = ini_get('memory_limit');
            if ($limit === '-1') return false;
            $limitBytes = self::parseMemoryLimit($limit);
            return ($limitBytes - memory_get_usage(true)) < self::$memoryThreshold;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function parseMemoryLimit(string $limit): int {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;
        return match ($last) {
            'g' => $value * 1073741824,
            'm' => $value * 1048576,
            'k' => $value * 1024,
            default => $value,
        };
    }

    private static function getMemoryUsage(): string {
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = ini_get('memory_limit');
        $fmt = function(int $b): string {
            if ($b >= 1073741824) return number_format($b/1073741824,2).' GB';
            if ($b >= 1048576) return number_format($b/1048576,2).' MB';
            if ($b >= 1024) return number_format($b/1024,2).' KB';
            return $b.' B';
        };
        return "当前: {$fmt($usage)} / 峰值: {$fmt($peak)} / 限制: {$limit}";
    }

    private static function cleanOutputBuffers(): void {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }

    private static function isDebugMode(): bool {
        $debug = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? false;
        if (is_string($debug)) {
            return in_array(strtolower($debug), ['true','1','yes','on'], true);
        }
        return (bool) $debug;
    }

    /**
     * 获取拦截的错误（供后续处理器读取）
     */
    public static function getInterceptedError(): ?array {
        return self::$interceptedError;
    }

    /**
     * 是否有拦截的错误
     */
    public static function hasIntercepted(): bool {
        return self::$interceptedError !== null;
    }

    /**
     * 重置状态
     */
    public static function reset(): void {
        self::$interceptedError = null;
        self::$isHandling = false;
        self::$executionId = substr(md5(uniqid('trace_early_', true)), 0, 12);
    }
}

// ========== 自动注册：在 Laravel 启动前抢先拦截 ==========
EarlyInterceptor::register();
