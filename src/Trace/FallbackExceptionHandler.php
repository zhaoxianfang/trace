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
 *
 * 处理场景：
 * 1. bootstrap/app.php 或配置加载异常（Laravel 未启动完成）
 * 2. 文件缓存损坏导致的解析错误
 * 3. 服务提供者 register/boot 阶段异常
 * 4. PHP 致命错误（Fatal Error）
 * 5. 内存耗尽（Out of Memory）
 * 6. 编译错误（Compile Error）
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

            // 记录日志
            EmergencyRenderer::logError($exception, 'Fatal Error Handler');

            // 尝试使用 Trace 处理器（如果可用）
            if (self::canUseTraceHandler()) {
                self::handleWithTrace($exception);
            } else {
                // 使用紧急渲染器
                $code = self::errorTypeToHttpCode($error['type']);
                EmergencyRenderer::render($exception, $code);
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

            // 尝试使用 trace 命名空间的统一错误视图
            if ($view->exists('trace::errors.error')) {
                $response = response()->view('trace::errors.error', [
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

            // 尝试使用通用错误视图（向后兼容）
            if ($view->exists('trace::errors.generic')) {
                $response = response()->view('trace::errors.generic', [
                    'code' => $code,
                    'message' => $message,
                    'exception' => $exception,
                    'isDebug' => self::isDebugMode(),
                    'requestId' => self::generateRequestId(),
                    'timestamp' => date('Y-m-d H:i:s'),
                ], $code);

                if (is_object($response) && method_exists($response, 'send')) {
                    $response->send();
                }
                return $response;
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
        return substr(md5(uniqid('', true)), 0, 12);
    }

    /**
     * 判断是否为致命错误类型
     */
    private static function isFatalErrorType(int $type): bool
    {
        return in_array($type, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
    }

    /**
     * 将错误类型转换为 HTTP 状态码
     */
    private static function errorTypeToHttpCode(int $type): int
    {
        return match ($type) {
            E_PARSE, E_COMPILE_ERROR => 500,
            default => 500,
        };
    }

    /**
     * 获取异常对应的 HTTP 状态码
     */
    private static function getHttpCode(Throwable $exception): int
    {
        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        $code = $exception->getCode();
        if ($code >= 100 && $code < 600) {
            return $code;
        }

        return 500;
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
