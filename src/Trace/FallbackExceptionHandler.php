<?php

namespace zxf\Trace;

use Throwable;

/**
 * 兜底异常处理器
 *
 * 用途：处理 Laravel 引导阶段异常、致命错误、以及框架无法正常工作的极端情况
 *
 * 设计原则：
 * 1. 与 Laravel 11+ 兼容 - 不覆盖 Laravel 的处理器，而是作为补充
 * 2. 只在极端情况下介入 - 当 Laravel 处理器无法工作时才生效
 * 3. 协作而非替换 - 保存原始处理器，必要时调用
 * 4. 多级降级 - 从最优方案逐步降级到最终兜底方案
 *
 * 处理场景：
 * 1. bootstrap/app.php 或配置加载异常（Laravel 未启动完成）
 * 2. 文件缓存损坏导致的解析错误
 * 3. 服务提供者 register/boot 阶段异常
 * 4. PHP 致命错误（Fatal Error）
 * 5. 内存耗尽（Out of Memory）
 * 6. 编译错误（Compile Error）
 * 7. 任意 composer 包引发的异常
 * 8. PHP 8.2+ 新增错误类型
 */
class FallbackExceptionHandler
{
    /**
     * 是否已经注册
     */
    private static bool $registered = false;

    /**
     * 是否正在处理错误（防止递归）
     */
    private static bool $isHandling = false;

    /**
     * 已处理的错误数量
     */
    private static int $handledCount = 0;

    /**
     * 最大处理次数（防止死循环）
     */
    private static int $maxHandleCount = 3;

    /**
     * Laravel 是否已启动完成
     */
    private static bool $laravelBooted = false;

    /**
     * 内存阈值（字节），低于此值时简化处理
     */
    private static int $memoryThreshold = 5242880; // 5MB

    /**
     * 注册兜底处理器
     *
     * 注意：此方法在 Laravel 完全启动前调用，因此需要特别小心
     *
     * @return void
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        // 只在 Web 环境下注册
        if (PHP_SAPI === 'cli') {
            return;
        }

        // 注册 shutdown 函数（用于捕获致命错误）
        // 这是唯一不会与 Laravel 冲突的注册方式
        register_shutdown_function([self::class, 'handleShutdown']);

        // 注册错误处理器（捕获警告和通知）
        set_error_handler([self::class, 'handleError']);

        self::$registered = true;
    }

    /**
     * 标记 Laravel 已启动完成
     *
     * 当 Laravel 启动完成后，兜底处理器会让位给 Laravel 的异常处理
     *
     * @return void
     */
    public static function markLaravelBooted(): void
    {
        self::$laravelBooted = true;
    }

    /**
     * 错误处理器 - 捕获非致命错误
     *
     * @param int $severity 错误级别
     * @param string $message 错误消息
     * @param string $file 文件路径
     * @param int $line 行号
     * @return bool 是否已处理
     */
    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        // 只处理特定类型的错误
        if (!in_array($severity, [E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE], true)) {
            return false; // 让 PHP 继续处理其他错误
        }

        // 在调试模式下记录警告
        if (self::isDebugMode()) {
            EmergencyRenderer::logError("[Warning] {$message} in {$file}:{$line}", 'ErrorHandler');
        }

        return true; // 表示已处理
    }

    /**
     * 处理 Shutdown（致命错误）
     *
     * 这是兜底处理器的核心功能，用于捕获 PHP 致命错误
     * 只在以下情况介入：
     * 1. 发生了致命错误
     * 2. Laravel 未启动完成，或 Laravel 处理器未能处理错误
     *
     * @return void
     */
    public static function handleShutdown(): void
    {
        // 防止递归
        if (self::$isHandling || self::$handledCount >= self::$maxHandleCount) {
            return;
        }

        $error = error_get_last();
        if ($error === null) {
            return;
        }

        // 只处理致命错误
        if (!self::isFatalErrorType($error['type'])) {
            return;
        }

        // 检查 Laravel 是否已处理此错误
        if (self::$laravelBooted && self::isLaravelHandlerActive()) {
            return;
        }

        // 检查内存使用情况
        $lowMemory = self::isLowMemory();

        self::$isHandling = true;
        self::$handledCount++;

        try {
            $exception = new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );

            // 记录日志（在低内存模式下简化）
            if (!$lowMemory) {
                EmergencyRenderer::logError($exception, 'Fatal Error Handler');
            }

            // 尝试使用 Trace 处理器（如果可用且内存充足）
            if (!$lowMemory && self::canUseTraceHandler()) {
                self::handleWithTrace($exception);
            } else {
                // 使用紧急渲染器（低内存模式下使用简化渲染）
                $code = self::errorTypeToHttpCode($error['type']);
                if ($lowMemory) {
                    self::renderMinimalError($exception, $code);
                } else {
                    EmergencyRenderer::render($exception, $code);
                }
            }
        } catch (Throwable $e) {
            // 如果连渲染都失败了，使用最后兜底方案
            self::lastResortOutput($e->getMessage());
        } finally {
            self::$isHandling = false;
        }

        exit(1);
    }

    /**
     * 处理引导阶段异常
     *
     * 此方法由 TraceServiceProvider 在注册时调用
     * 用于处理 Laravel 启动过程中的异常
     *
     * @param Throwable $exception
     * @return void
     */
    public static function handleBootstrapException(Throwable $exception): void
    {
        // 防止递归
        if (self::$isHandling || self::$handledCount >= self::$maxHandleCount) {
            return;
        }

        self::$isHandling = true;
        self::$handledCount++;

        try {
            // 记录日志
            EmergencyRenderer::logError($exception, 'Bootstrap Exception Handler');

            // 尝试使用 Trace 处理器（如果可用）
            if (self::canUseTraceHandler()) {
                self::handleWithTrace($exception);
            } else {
                // 使用紧急渲染器
                $code = self::getHttpCode($exception);
                EmergencyRenderer::render($exception, $code);
            }
        } catch (Throwable $e) {
            // 如果连渲染都失败了
            EmergencyRenderer::render($e, 500);
        } finally {
            self::$isHandling = false;
        }

        exit(1);
    }

    /**
     * 处理任意异常（供外部调用）
     *
     * @param Throwable $exception
     * @return void
     */
    public static function handleException(Throwable $exception): void
    {
        // 防止递归
        if (self::$isHandling || self::$handledCount >= self::$maxHandleCount) {
            return;
        }

        self::$isHandling = true;
        self::$handledCount++;

        try {
            // 记录日志
            EmergencyRenderer::logError($exception, 'Exception Handler');

            // 检查内存
            $lowMemory = self::isLowMemory();

            // 尝试使用 Trace 处理器
            if (!$lowMemory && self::canUseTraceHandler()) {
                self::handleWithTrace($exception);
            } else {
                // 使用紧急渲染器
                $code = self::getHttpCode($exception);
                if ($lowMemory) {
                    self::renderMinimalError($exception, $code);
                } else {
                    EmergencyRenderer::render($exception, $code);
                }
            }
        } catch (Throwable $e) {
            // 如果连渲染都失败了
            self::lastResortOutput($e->getMessage());
        } finally {
            self::$isHandling = false;
        }

        exit(1);
    }

    /**
     * 检查内存是否不足
     *
     * @return bool
     */
    private static function isLowMemory(): bool
    {
        try {
            $memoryLimit = ini_get('memory_limit');
            if ($memoryLimit === '-1') {
                return false; // 无限制
            }

            $memoryLimitBytes = self::parseMemoryLimit($memoryLimit);
            $currentUsage = memory_get_usage(true);
            $available = $memoryLimitBytes - $currentUsage;

            return $available < self::$memoryThreshold;
        } catch (Throwable) {
            return false; // 默认认为内存充足
        }
    }

    /**
     * 解析内存限制字符串为字节数
     *
     * @param string $limit
     * @return int
     */
    private static function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;

        return match ($last) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /**
     * 渲染极简错误（低内存模式下）
     *
     * @param Throwable $exception
     * @param int $code
     * @return void
     */
    private static function renderMinimalError(Throwable $exception, int $code): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: text/html; charset=utf-8');
        }

        $safeMessage = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
        $isDebug = self::isDebugMode();

        // 极简 HTML，占用最少内存
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Error ', $code, '</title><style>body{font-family:system-ui,sans-serif;background:#f5f5f5;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;padding:20px}.box{background:#fff;padding:40px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.1);text-align:center;max-width:400px}.code{font-size:48px;font-weight:700;color:#e53e3e;margin-bottom:10px}.msg{color:#666;margin:20px 0}.btn{background:#667eea;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;display:inline-block}</style></head><body><div class="box"><div class="code">', $code, '</div><h2>System Error</h2><p class="msg">', $safeMessage, '</p><a href="/" class="btn">返回首页</a></div></body></html>';
    }

    /**
     * 检查 Laravel 处理器是否已激活
     *
     * @return bool
     */
    private static function isLaravelHandlerActive(): bool
    {
        try {
            // 检查是否已经有响应被发送
            if (function_exists('headers_sent') && headers_sent()) {
                return true;
            }

            // 检查 Laravel 的异常处理器是否已注册
            $previousHandler = set_exception_handler(null);
            if ($previousHandler !== null) {
                // 恢复处理器
                restore_exception_handler();
                return true;
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * 判断是否可以使用 Trace 处理器
     */
    private static function canUseTraceHandler(): bool
    {
        try {
            // 检查容器是否可用
            if (!function_exists('app')) {
                return false;
            }

            $app = app();

            // 检查 trace 服务是否绑定
            if (!$app->bound('trace')) {
                return false;
            }

            // 获取 trace 实例
            $trace = app('trace');

            // 检查必要的方法是否存在
            if (!method_exists($trace, 'initError') || !method_exists($trace, 'respJson')) {
                return false;
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * 使用 Trace 处理器处理异常
     */
    private static function handleWithTrace(Throwable $exception): void
    {
        try {
            $trace = app('trace');
            $trace->initError($exception);

            // 判断请求类型
            $isAjax = self::isAjaxRequest();
            $isDebug = self::isDebugMode();

            if ($isDebug && !$isAjax) {
                // 调试模式返回详细错误
                $response = $trace->debug($exception);
            } elseif ($isAjax) {
                $response = $trace->respJson($trace::$message, $trace::$code);
            } else {
                // 尝试使用 Blade 视图
                $response = self::renderWithBlade($trace::$message, $trace::$code, $exception);
            }

            // 输出响应
            if (is_object($response) && method_exists($response, 'send')) {
                $response->send();
            } else {
                echo $response;
            }
        } catch (Throwable $e) {
            // 如果 Trace 处理失败，降级到紧急渲染
            EmergencyRenderer::render($e, 500);
        }
    }

    /**
     * 使用 Blade 视图渲染错误页面
     *
     * @param string $message
     * @param int $code
     * @param Throwable $exception
     * @return mixed
     */
    private static function renderWithBlade(string $message, int $code, Throwable $exception): mixed
    {
        try {
            // 检查视图系统是否可用 - 多重检查确保安全性
            if (!function_exists('view') || !function_exists('app') || !app()->bound('view')) {
                // 降级到 respView
                if (self::canUseTraceHandler()) {
                    $trace = app('trace');
                    return $trace->respView($message, $code);
                }
                // 如果连 Trace 处理器都不可用，使用紧急渲染器
                EmergencyRenderer::render($exception, $code);
                exit(1);
            }

            // 获取视图工厂实例
            $view = app('view');

            // 检查 trace 命名空间是否已注册
            $hasTraceNamespace = false;
            try {
                $namespaces = $view->getFinder()->getHints();
                $hasTraceNamespace = isset($namespaces['trace']);
            } catch (\Throwable $e) {
                // 无法获取命名空间信息，继续尝试
            }

            // 如果 trace 命名空间未注册，尝试动态注册
            if (!$hasTraceNamespace) {
                $traceViewPath = __DIR__ . '/../Resources/views';
                if (is_dir($traceViewPath)) {
                    $view->addNamespace('trace', $traceViewPath);
                }
            }

            // 按优先级尝试使用不同的视图
            $viewPriorities = [
                'trace::errors.unified',    // 新的统一视图
                'trace::errors.error',      // 通用错误视图
                'trace::errors.generic',    // 兼容旧版本
                'trace::emergency',         // 紧急视图
            ];

            foreach ($viewPriorities as $viewName) {
                if ($view->exists($viewName)) {
                    $response = response()->view($viewName, [
                        'code' => $code,
                        'message' => $message,
                        'exception' => $exception,
                        'isDebug' => self::isDebugMode(),
                        'requestId' => self::generateRequestId(),
                        'timestamp' => date('Y-m-d H:i:s'),
                    ], $code);

                    // 确保响应被正确发送
                    if (is_object($response) && method_exists($response, 'send')) {
                        $response->send();
                    }
                    return $response;
                }
            }

            // 降级到 respView
            if (self::canUseTraceHandler()) {
                $trace = app('trace');
                return $trace->respView($message, $code);
            }

            // 最终兜底：使用紧急渲染器
            EmergencyRenderer::render($exception, $code);
            exit(1);
        } catch (Throwable $e) {
            // 如果视图渲染失败，记录错误并使用紧急渲染器
            EmergencyRenderer::logError($e, 'FallbackExceptionHandler::renderWithBlade');
            EmergencyRenderer::render($exception, $code);
            exit(1);
        }
    }

    /**
     * 生成请求 ID
     */
    private static function generateRequestId(): string
    {
        try {
            // 尝试使用随机字节生成更安全的请求ID
            if (function_exists('random_bytes')) {
                return substr(bin2hex(random_bytes(8)), 0, 12);
            }
        } catch (Throwable) {
            // 回退到传统方式
        }
        return substr(md5(uniqid('', true)), 0, 12);
    }

    /**
     * 判断是否为致命错误类型
     */
    private static function isFatalErrorType(int $type): bool
    {
        // 包含 PHP 8+ 新增的错误类型
        $fatalTypes = [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_USER_ERROR,
        ];

        // PHP 8.0+ 新增的错误类型
        if (defined('E_STRICT') && PHP_VERSION_ID >= 80000) {
            // PHP 8+ 将 E_STRICT 合并到其他错误类型中
        }

        return in_array($type, $fatalTypes, true);
    }

    /**
     * 将错误类型转换为 HTTP 状态码
     */
    private static function errorTypeToHttpCode(int $type): int
    {
        return match ($type) {
            E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR => 500,
            default => 500,
        };
    }

    /**
     * 获取异常对应的 HTTP 状态码
     */
    private static function getHttpCode(Throwable $exception): int
    {
        // 检查是否为 HTTP 异常
        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        // 检查异常代码是否在有效范围内
        $code = $exception->getCode();
        if ($code >= 100 && $code < 600) {
            return $code;
        }

        // 根据异常类型推断状态码
        $class = get_class($exception);
        return match (true) {
            str_contains($class, 'NotFound') => 404,
            str_contains($class, 'Unauthorized') => 401,
            str_contains($class, 'Forbidden') => 403,
            str_contains($class, 'Validation') => 422,
            str_contains($class, 'MethodNotAllowed') => 405,
            default => 500,
        };
    }

    /**
     * 判断是否为 AJAX 请求
     */
    private static function isAjaxRequest(): bool
    {
        $xRequestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (strtolower($xRequestedWith) === 'xmlhttprequest') {
            return true;
        }

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json');
    }

    /**
     * 判断是否处于调试模式
     */
    private static function isDebugMode(): bool
    {
        // 多维度检查调试模式
        $debug = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? false;

        if (is_string($debug)) {
            return in_array(strtolower($debug), ['true', '1', 'yes', 'on'], true);
        }

        return (bool) $debug;
    }

    /**
     * 最后的输出方案（当所有其他方案都失败时）
     *
     * 优先尝试使用 Blade 视图，失败时使用极简内联 HTML
     */
    private static function lastResortOutput(string $message): void
    {
        // 清除所有输出缓冲
        $level = ob_get_level();
        for ($i = 0; $i < $level; $i++) {
            @ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        // 尝试使用 Blade 视图
        try {
            if (function_exists('view') && function_exists('app') && app()->bound('view')) {
                $view = app('view');
                $viewPath = __DIR__ . '/../Resources/views';

                // 确保 trace 命名空间已注册
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

                // 尝试使用 unified 视图
                if ($view->exists('trace::errors.unified')) {
                    echo $view->make('trace::errors.unified', [
                        'code' => 500,
                        'title' => 'System Error',
                        'message' => $safeMessage,
                        'mode' => 'minimal',
                    ])->render();
                    return;
                }

                // 尝试使用 minimal 视图
                if ($view->exists('trace::errors.minimal')) {
                    echo $view->make('trace::errors.minimal', [
                        'code' => 500,
                        'title' => 'System Error',
                        'message' => $safeMessage,
                    ])->render();
                    return;
                }

                // 尝试使用 emergency 视图
                if ($view->exists('trace::emergency')) {
                    echo $view->make('trace::emergency', [
                        'code' => 500,
                        'title' => 'System Error',
                        'message' => $safeMessage,
                        'emoji' => '💥',
                    ])->render();
                    return;
                }
            }
        } catch (\Throwable $e) {
            // Blade 失败，使用内联模板
        }

        // 最终兜底：极简内联 HTML
        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Error - 500</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: rgba(255,255,255,0.98); border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 500px; width: 100%; padding: 40px; text-align: center; }
        .code { font-size: 72px; font-weight: bold; color: #e53e3e; margin-bottom: 15px; }
        .title { font-size: 22px; color: #2d3748; margin-bottom: 15px; }
        .message { font-size: 15px; color: #718096; margin-bottom: 25px; line-height: 1.6; word-break: break-word; }
        .btn { display: inline-block; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-size: 14px; background: #667eea; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="code">500</div>
        <h1 class="title">System Error</h1>
        <p class="message">{$safeMessage}</p>
        <a href="/" class="btn">返回首页</a>
    </div>
</body>
</html>
HTML;
    }
}
