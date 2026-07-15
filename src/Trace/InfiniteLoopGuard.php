<?php

namespace zxf\Trace;

/**
 * 死循环防护器
 *
 * 用途：检测和防止 Laravel 11+ 项目中的死循环和特殊异常情况
 *
 * 功能：
 * 1. 检测递归调用深度，防止无限递归
 * 2. 监控执行时间和内存使用
 * 3. 处理超时和内存耗尽错误
 * 4. 提供安全的错误恢复机制
 */
class InfiniteLoopGuard
{
    /**
     * 递归调用栈深度跟踪
     */
    private static array $callStack = [];

    /**
     * 最大递归深度
     */
    private static int $maxRecursionDepth = 50;

    /**
     * 方法调用计数器
     */
    private static array $methodCallCount = [];

    /**
     * 最大方法调用次数
     */
    private static int $maxMethodCalls = 1000;

    /**
     * 开始时间
     */
    private static ?float $startTime = null;

    /**
     * 最大执行时间（秒）
     */
    private static float $maxExecutionTime = 30.0;

    /**
     * 是否已注册 shutdown 处理
     */
    private static bool $shutdownRegistered = false;

    /**
     * 初始化防护器
     */
    public static function init(): void
    {
        if (self::$startTime === null) {
            self::$startTime = microtime(true);
        }

        // 注册 shutdown 函数来处理超时/内存错误
        if (!self::$shutdownRegistered) {
            register_shutdown_function([self::class, 'handleShutdown']);
            self::$shutdownRegistered = true;
        }

        // 设置内存限制监控（保留 5MB 缓冲）
        $memoryLimit = self::getMemoryLimitBytes();
        if ($memoryLimit > 0) {
            $safeLimit = $memoryLimit - (5 * 1024 * 1024); // 5MB 缓冲
            if ($safeLimit > 0) {
                // 设置内存告警阈值
                if (function_exists('memory_get_usage')) {
                    $currentMemory = memory_get_usage(true);
                    if ($currentMemory > $safeLimit * 0.95) {
                        self::triggerEmergencyResponse('内存使用接近限制', 500);
                    }
                }
            }
        }
    }

    /**
     * 进入方法（增加调用计数）
     *
     * @param string $method 方法名
     * @return bool 是否允许继续执行
     */
    public static function enter(string $method): bool
    {
        self::init();

        // 检查递归深度
        $depth = count(self::$callStack);
        if ($depth >= self::$maxRecursionDepth) {
            self::triggerEmergencyResponse("递归深度超过限制: {$method}", 500);
            return false;
        }

        // 检查方法调用次数
        if (!isset(self::$methodCallCount[$method])) {
            self::$methodCallCount[$method] = 0;
        }
        self::$methodCallCount[$method]++;

        if (self::$methodCallCount[$method] > self::$maxMethodCalls) {
            self::triggerEmergencyResponse("方法调用次数超过限制: {$method}", 500);
            return false;
        }

        // 检查执行时间
        if (self::$startTime !== null) {
            $elapsed = microtime(true) - self::$startTime;
            if ($elapsed > self::$maxExecutionTime) {
                self::triggerEmergencyResponse('执行时间超过限制', 504);
                return false;
            }
        }

        // 压入调用栈
        self::$callStack[] = [
            'method' => $method,
            'time' => microtime(true),
        ];

        return true;
    }

    /**
     * 退出方法
     */
    public static function exit(): void
    {
        array_pop(self::$callStack);
    }

    /**
     * 执行方法（自动包装进入和退出）
     *
     * @param string $method 方法名
     * @param callable $callback 要执行的回调
     * @param mixed ...$args 参数
     * @return mixed
     */
    public static function execute(string $method, callable $callback, ...$args)
    {
        if (!self::enter($method)) {
            throw new \RuntimeException("死循环防护: {$method} 被阻止");
        }

        try {
            return $callback(...$args);
        } finally {
            self::exit();
        }
    }

    /**
     * 处理 shutdown（超时/内存错误）
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        // 只处理致命错误
        $fatalErrors = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($error['type'], $fatalErrors, true)) {
            return;
        }

        // 检查是否是内存耗尽或超时
        $isMemoryError = str_contains($error['message'], 'Allowed memory size');
        $isTimeoutError = str_contains($error['message'], 'Maximum execution time');

        if ($isMemoryError || $isTimeoutError) {
            $code = $isMemoryError ? 507 : 504;
            $message = $isMemoryError ? '服务器内存不足' : '请求处理超时';

            // 清除输出缓冲
            self::cleanOutputBuffers();

            // 发送错误响应
            if (!headers_sent()) {
                http_response_code($code);
                header('Content-Type: text/html; charset=utf-8');
            }

            // 使用最小化的错误输出
            echo self::getMinimalErrorHtml($code, $message);
        }
    }

    /**
     * 获取最小化的错误 HTML
     */
    private static function getMinimalErrorHtml(int $code, string $message): string
    {
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$safeMessage}</title>
    <style>
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:linear-gradient(135deg,#667eea,#764ba2);min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0}
        .container{background:#fff;border-radius:20px;padding:40px;text-align:center;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,0.3)}
        .code{font-size:72px;font-weight:bold;color:#e53e3e;margin-bottom:10px}
        .title{font-size:24px;color:#333;margin-bottom:15px}
        .message{color:#666;margin-bottom:20px}
        a{background:#667eea;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block}
    </style>
</head>
<body>
    <div class="container">
        <div class="code">{$code}</div>
        <h1 class="title">{$safeMessage}</h1>
        <p class="message">请稍后重试或联系管理员</p>
        <a href="/">返回首页</a>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 触发紧急响应
     */
    private static function triggerEmergencyResponse(string $reason, int $code): void
    {
        // 清除所有输出缓冲
        self::cleanOutputBuffers();

        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: text/html; charset=utf-8');
        }

        $html = self::getMinimalErrorHtml($code, $reason);
        echo $html;

        // 记录错误
        error_log("[InfiniteLoopGuard] {$reason}");

        exit(1);
    }

    /**
     * 清除输出缓冲
     */
    private static function cleanOutputBuffers(): void
    {
        $level = ob_get_level();
        for ($i = 0; $i < $level && $i < 10; $i++) {
            @ob_end_clean();
        }
    }

    /**
     * 获取内存限制（字节）
     */
    private static function getMemoryLimitBytes(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return -1; // 无限制
        }

        $limit = trim($limit);
        if ($limit === '') {
            return -1; // 无法解析，视为无限制
        }
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
     * 重置所有计数器
     */
    public static function reset(): void
    {
        self::$callStack = [];
        self::$methodCallCount = [];
        self::$startTime = null;
    }

    /**
     * 设置最大递归深度
     */
    public static function setMaxRecursionDepth(int $depth): void
    {
        self::$maxRecursionDepth = $depth;
    }

    /**
     * 设置最大执行时间
     */
    public static function setMaxExecutionTime(float $seconds): void
    {
        self::$maxExecutionTime = $seconds;
    }
}
