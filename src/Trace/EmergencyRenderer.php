<?php

namespace zxf\Trace;

/**
 * 紧急错误渲染器
 *
 * 用途：当 Laravel 框架完全无法工作时（如引导阶段异常、致命错误），
 * 提供独立的错误渲染能力，不依赖任何框架功能。
 *
 * 特点：
 * 1. 零依赖 - 不依赖 Laravel 的任何组件
 * 2. 原生 PHP - 使用最基础的 PHP 函数
 * 3. 防递归 - 防止错误处理过程中再次出错
 * 4. 多格式 - 支持 HTML、JSON、纯文本输出
 */
class EmergencyRenderer
{
    /**
     * 标记是否正在渲染，防止递归
     */
    private static bool $isRendering = false;

    /**
     * 是否已经发送过响应，防止重复输出
     */
    private static bool $responseSent = false;

    /**
     * 渲染紧急错误响应
     *
     * @param \Throwable|string $error 异常对象或错误消息
     * @param int $code HTTP 状态码
     * @param string|null $format 强制格式 (html|json|text)，null 则自动检测
     * @return void
     */
    public static function render(\Throwable|string $error, int $code = 500, ?string $format = null): void
    {
        // 防止递归调用
        if (self::$isRendering || self::$responseSent) {
            return;
        }

        // 验证状态码范围
        if ($code < 100 || $code > 599) {
            $code = 500;
        }

        self::$isRendering = true;

        try {
            // 清除所有输出缓冲
            self::cleanOutputBuffers();

            // 如果 headers 还没发送，设置 HTTP 状态码和内容类型
            if (! headers_sent()) {
                http_response_code($code);
            }

            // 自动检测格式
            if ($format === null) {
                $format = self::detectFormat();
            }

            // 验证格式
            if (!in_array($format, ['html', 'json', 'text'], true)) {
                $format = 'html';
            }

            // 获取错误信息
            $errorInfo = self::extractErrorInfo($error);

            // 根据格式渲染响应
            switch ($format) {
                case 'json':
                    self::renderJson($errorInfo, $code);
                    break;
                case 'text':
                    self::renderText($errorInfo, $code);
                    break;
                case 'html':
                default:
                    self::renderHtml($errorInfo, $code);
                    break;
            }

            self::$responseSent = true;

        } catch (\Throwable $e) {
            // 如果连紧急渲染都失败了，使用最后的兜底方案
            try {
                self::renderFallback($e->getMessage(), $code);
            } catch (\Throwable $finalError) {
                // 如果连最后的兜底方案都失败了，直接输出最简单的错误信息
                echo "Error {$code}: System Error";
            }
        } finally {
            self::$isRendering = false;
        }
    }

    /**
     * 检测客户端期望的响应格式
     */
    private static function detectFormat(): string
    {
        // 检查 Accept 头
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        if (str_contains($accept, 'application/json')) {
            return 'json';
        }

        if (str_contains($accept, 'text/plain')) {
            return 'text';
        }

        // 检查是否是 AJAX 请求
        $xRequestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (strtolower($xRequestedWith) === 'xmlhttprequest') {
            return 'json';
        }

        // 检查请求路径
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (str_contains($uri, '/api/') || str_ends_with($uri, '.json')) {
            return 'json';
        }

        return 'html';
    }

    /**
     * 提取错误信息
     */
    private static function extractErrorInfo(\Throwable|string $error): array
    {
        if ($error instanceof \Throwable) {
            return [
                'message' => $error->getMessage() ?: '系统发生错误',
                'code' => $error->getCode() ?: 500,
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'trace' => $error->getTraceAsString(),
                'type' => get_class($error),
            ];
        }

        return [
            'message' => $error ?: '系统发生错误',
            'code' => 500,
            'file' => '',
            'line' => 0,
            'trace' => '',
            'type' => 'Error',
        ];
    }

    /**
     * 渲染 JSON 响应
     */
    private static function renderJson(array $errorInfo, int $code): void
    {
        if (! headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $isDebug = self::isDebugMode();

        $response = [
            'success' => false,
            'code' => $code,
            'message' => self::getPublicMessage($errorInfo['message'], $code),
        ];

        if ($isDebug) {
            $response['debug'] = [
                'type' => $errorInfo['type'],
                'original_message' => $errorInfo['message'],
                'file' => $errorInfo['file'],
                'line' => $errorInfo['line'],
                'trace' => explode("\n", $errorInfo['trace']),
            ];
        }

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * 渲染纯文本响应
     */
    private static function renderText(array $errorInfo, int $code): void
    {
        if (! headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }

        $isDebug = self::isDebugMode();
        $message = self::getPublicMessage($errorInfo['message'], $code);

        echo "Error {$code}: {$message}\n";

        if ($isDebug) {
            echo "\nType: {$errorInfo['type']}\n";
            echo "Message: {$errorInfo['message']}\n";
            echo "File: {$errorInfo['file']}:{$errorInfo['line']}\n";
            echo "\nStack Trace:\n{$errorInfo['trace']}\n";
        }
    }

    /**
     * 渲染 HTML 响应
     *
     * 优先尝试使用 Blade 视图，如果不可用则使用内联模板
     */
    private static function renderHtml(array $errorInfo, int $code): void
    {
        if (! headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        $isDebug = self::isDebugMode();
        $publicMessage = self::getPublicMessage($errorInfo['message'], $code);

        // 尝试使用 Blade 视图（如果 Laravel 可用）
        if (self::tryRenderBladeView($errorInfo, $code, $isDebug, $publicMessage)) {
            return;
        }

        // 降级到内联模板
        self::renderInlineTemplate($errorInfo, $code, $isDebug, $publicMessage);
    }

    /**
     * 尝试使用 Blade 视图渲染
     *
     * @return bool 是否成功渲染
     */
    private static function tryRenderBladeView(array $errorInfo, int $code, bool $isDebug, string $publicMessage): bool
    {
        try {
            // 检查 Laravel 视图系统是否可用
            if (!function_exists('view') || !function_exists('app')) {
                return false;
            }

            $app = app();
            if (!$app->bound('view')) {
                return false;
            }

            $view = $app->make('view');

            // 确保 trace 命名空间已注册
            $viewPath = __DIR__ . '/../Resources/views';
            if (is_dir($viewPath)) {
                try {
                    $namespaces = $view->getFinder()->getHints();
                    if (!isset($namespaces['trace'])) {
                        $view->addNamespace('trace', $viewPath);
                    }
                } catch (\Throwable $e) {
                    // 如果无法获取命名空间，尝试直接添加
                    $view->addNamespace('trace', $viewPath);
                }
            }

            // 检查视图是否存在
            if (!$view->exists('trace::error')) {
                return false;
            }

            // 准备视图数据
            $viewData = [
                'code' => $code,
                'title' => self::getErrorTitle($code),
                'message' => $publicMessage,
                'requestId' => self::generateRequestId(),
                'timestamp' => date('Y-m-d H:i:s'),
                'isDebug' => $isDebug,
            ];

            // 如果有异常对象，添加到视图数据
            // 使用模拟异常对象来传递调试信息
            if ($isDebug) {
                $viewData['exception'] = new class($errorInfo) extends \Exception {
                    private array $info;

                    public function __construct(array $info)
                    {
                        // 使用 ReflectionClass 来设置 protected 属性
                        parent::__construct($info['message'], $info['code']);
                        $this->info = $info;

                        // 使用反射设置 file 和 line
                        try {
                            $reflection = new \ReflectionClass(\Exception::class);

                            $fileProp = $reflection->getProperty('file');
                            $fileProp->setAccessible(true);
                            $fileProp->setValue($this, $info['file'] ?? '');

                            $lineProp = $reflection->getProperty('line');
                            $lineProp->setAccessible(true);
                            $lineProp->setValue($this, $info['line'] ?? 0);

                            // trace 是字符串，需要转换为数组
                            $traceString = $info['trace'] ?? '';
                            $trace = [];
                            if (!empty($traceString)) {
                                $lines = explode("\n", $traceString);
                                foreach ($lines as $line) {
                                    if (preg_match('/^#(\d+)\s+(.+)$/', $line, $matches)) {
                                        $trace[] = ['file' => $matches[2], 'line' => 0, 'function' => '', 'class' => ''];
                                    }
                                }
                            }

                            $traceProp = $reflection->getProperty('trace');
                            $traceProp->setAccessible(true);
                            $traceProp->setValue($this, $trace);
                        } catch (\Throwable $e) {
                            // 反射失败，忽略
                        }
                    }
                };
            }

            // 渲染视图
            echo $view->make('trace::error', $viewData)->render();
            return true;

        } catch (\Throwable $e) {
            // Blade 渲染失败，返回 false 让调用方使用内联模板
            return false;
        }
    }

    /**
     * 使用内联模板渲染（零依赖兜底方案）
     */
    private static function renderInlineTemplate(array $errorInfo, int $code, bool $isDebug, string $publicMessage): void
    {
        $title = self::getErrorTitle($code);
        $emoji = self::getErrorEmoji($code);
        $requestId = self::generateRequestId();
        $timestamp = date('Y-m-d H:i:s');

        $debugSection = '';
        if ($isDebug) {
            $debugType = htmlspecialchars($errorInfo['type'], ENT_QUOTES, 'UTF-8');
            $debugMessage = htmlspecialchars($errorInfo['message'], ENT_QUOTES, 'UTF-8');
            $debugFile = htmlspecialchars($errorInfo['file'], ENT_QUOTES, 'UTF-8');
            $debugLine = $errorInfo['line'];
            $debugTrace = htmlspecialchars($errorInfo['trace'], ENT_QUOTES, 'UTF-8');

            $debugSection = <<<DEBUG
        <div class="debug-info" style="margin-top:30px;text-align:left;background:#1a202c;border-radius:10px;overflow:hidden;">
            <div style="background:#2d3748;padding:12px 20px;color:#fff;font-size:14px;font-weight:600;">调试信息</div>
            <div style="padding:20px;color:#e2e8f0;font-family:Consolas,Monospace;font-size:13px;line-height:1.6;">
                <div style="margin-bottom:10px;"><span style="color:#68d391;font-weight:600;">类型:</span> {$debugType}</div>
                <div style="margin-bottom:10px;"><span style="color:#68d391;font-weight:600;">消息:</span> {$debugMessage}</div>
                <div style="margin-bottom:10px;"><span style="color:#68d391;font-weight:600;">文件:</span> {$debugFile}:{$debugLine}</div>
                <div style="margin-top:15px;padding-top:15px;border-top:1px solid #4a5568;white-space:pre-wrap;word-break:break-all;max-height:300px;overflow-y:auto;">{$debugTrace}</div>
            </div>
        </div>
DEBUG;
        }

        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{$title} - {$code}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: rgba(255,255,255,0.98); border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 600px; width: 100%; padding: 40px; text-align: center; animation: slideIn 0.5s ease-out; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-30px); } to { opacity: 1; transform: translateY(0); } }
        .emoji { font-size: 70px; margin-bottom: 15px; display: block; }
        .code { font-size: 72px; font-weight: bold; color: #e53e3e; line-height: 1; margin-bottom: 10px; }
        .title { font-size: 24px; color: #2d3748; margin-bottom: 15px; font-weight: 600; }
        .message { font-size: 16px; color: #718096; margin-bottom: 30px; line-height: 1.6; }
        .actions { display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; }
        .btn { padding: 12px 24px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; transition: all 0.3s ease; border: none; cursor: pointer; display: inline-block; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102,126,234,0.4); }
        .btn-secondary { background: #edf2f7; color: #4a5568; }
        .btn-secondary:hover { background: #e2e8f0; }
        .meta-info { margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #a0aec0; }
        @media (max-width: 480px) { .container { padding: 30px 20px; } .code { font-size: 56px; } .emoji { font-size: 56px; } .title { font-size: 20px; } }
    </style>
</head>
<body>
    <div class="container">
        <span class="emoji">{$emoji}</span>
        <div class="code">{$code}</div>
        <h1 class="title">{$title}</h1>
        <p class="message">{$publicMessage}</p>
        <div class="actions">
            <a href="/" class="btn btn-primary">返回首页</a>
            <button onclick="location.reload()" class="btn btn-secondary">刷新页面</button>
        </div>
        {$debugSection}
        <div class="meta-info">请求ID: {$requestId} | 时间: {$timestamp}</div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 最后的兜底渲染方案（当所有其他方案都失败时）
     *
     * 这是最简单的 HTML 输出，确保在任何情况下都能显示错误信息
     */
    private static function renderFallback(string $message, int $code): void
    {
        if (! headers_sent()) {
            http_response_code($code);
            header('Content-Type: text/html; charset=utf-8');
        }

        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $title = self::getErrorTitle($code);

        // 优先尝试使用 Blade 视图
        try {
            if (function_exists('view') && function_exists('app') && app()->bound('view')) {
                $view = app('view');
                $viewPath = __DIR__ . '/../Resources/views';

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

                if ($view->exists('trace::errors.minimal')) {
                    echo $view->make('trace::errors.minimal', [
                        'code' => $code,
                        'title' => $title,
                        'message' => $safeMessage,
                    ])->render();
                    return;
                }
            }
        } catch (\Throwable $e) {
            // Blade 失败，继续到内联模板
        }

        // 最终兜底：极简内联 HTML
        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - {$code}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: rgba(255,255,255,0.98); border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 500px; width: 100%; padding: 40px; text-align: center; }
        .code { font-size: 72px; font-weight: bold; color: #e53e3e; margin-bottom: 15px; }
        .title { font-size: 22px; color: #2d3748; margin-bottom: 15px; }
        .message { font-size: 15px; color: #718096; margin-bottom: 25px; line-height: 1.6; }
        .btn { display: inline-block; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-size: 14px; background: #667eea; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="code">{$code}</div>
        <h1 class="title">{$title}</h1>
        <p class="message">{$safeMessage}</p>
        <a href="/" class="btn">返回首页</a>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 清除所有输出缓冲
     */
    private static function cleanOutputBuffers(): void
    {
        try {
            // 获取当前输出缓冲级别
            $level = ob_get_level();
            // 防止死循环，设置最大尝试次数
            $maxAttempts = 10;
            $attempts = 0;
            
            while ($level > 0 && $attempts < $maxAttempts) {
                @ob_end_clean();
                $newLevel = ob_get_level();
                // 如果级别没有变化，说明清理失败，跳出循环
                if ($newLevel === $level) {
                    break;
                }
                $level = $newLevel;
                $attempts++;
            }
        } catch (\Throwable $e) {
            // 忽略输出缓冲清理错误
        }
    }

    /**
     * 判断是否处于调试模式
     */
    private static function isDebugMode(): bool
    {
        // 检查环境变量
        $debug = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? false;

        if (is_string($debug)) {
            return in_array(strtolower($debug), ['true', '1', 'yes', 'on'], true);
        }

        return (bool) $debug;
    }

    /**
     * 获取公开友好的错误消息
     */
    private static function getPublicMessage(string $message, int $code): string
    {
        // 如果是调试模式，返回原始消息
        if (self::isDebugMode()) {
            return $message;
        }

        // 生产环境返回友好的预设消息
        $messages = [
            400 => '请求参数错误，请检查输入',
            401 => '请先登录后再访问',
            403 => '您没有权限访问此页面',
            404 => '页面不存在或已被移除',
            405 => '请求方法不被允许',
            422 => '提交的数据验证失败',
            429 => '请求过于频繁，请稍后再试',
            500 => '服务器内部错误，请稍后重试',
            502 => '网关错误，请稍后重试',
            503 => '服务暂时不可用，请稍后重试',
        ];

        return $messages[$code] ?? '系统发生错误，请稍后重试或联系管理员';
    }

    /**
     * 获取错误标题
     */
    private static function getErrorTitle(int $code): string
    {
        $titles = [
            400 => '请求错误',
            401 => '未授权',
            403 => '禁止访问',
            404 => '页面未找到',
            405 => '方法不允许',
            408 => '请求超时',
            422 => '无法处理',
            429 => '请求过多',
            500 => '服务器错误',
            502 => '网关错误',
            503 => '服务不可用',
            504 => '网关超时',
        ];

        return $titles[$code] ?? '系统错误';
    }

    /**
     * 获取错误表情符号
     */
    private static function getErrorEmoji(int $code): string
    {
        $emojis = [
            400 => '🤔',
            401 => '🔒',
            403 => '🚫',
            404 => '🤷',
            405 => '🙅',
            408 => '⏱️',
            422 => '📝',
            429 => '🐢',
            500 => '💥',
            502 => '🔌',
            503 => '🔧',
            504 => '⏳',
        ];

        return $emojis[$code] ?? '⚠️';
    }

    /**
     * 生成请求 ID
     */
    private static function generateRequestId(): string
    {
        return substr(md5(uniqid('', true)), 0, 12);
    }

    /**
     * 记录紧急错误日志（当框架日志不可用时）
     *
     * @param \Throwable|string $error
     * @param string $context 错误上下文
     * @return void
     */
    public static function logError(\Throwable|string $error, string $context = ''): void
    {
        try {
            $message = $error instanceof \Throwable ? $error->getMessage() : (string)$error;
            $trace = '';
            
            if ($error instanceof \Throwable) {
                // 限制堆栈跟踪长度，防止内存问题
                $fullTrace = $error->getTraceAsString();
                $trace = substr($fullTrace, 0, 2000);
                if (strlen($fullTrace) > 2000) {
                    $trace .= '... [TRUNCATED]';
                }
            }

            // 转义特殊字符，防止日志注入
            $safeMessage = str_replace(["\r", "\n"], ['', ' '], $message);
            $safeContext = str_replace(["\r", "\n"], ['', ' '], $context);

            $logMessage = sprintf(
                "[%s] [EMERGENCY] %s | Context: %s\n",
                date('Y-m-d H:i:s'),
                $safeMessage,
                $safeContext
            );

            // 尝试写入系统日志
            @error_log($logMessage);

            // 尝试写入文件（多种路径尝试）
            self::writeToLogFile($logMessage);
        } catch (\Throwable $e) {
            // 忽略所有日志记录错误，防止递归
        }
    }

    /**
     * 尝试将日志写入文件（多种路径）
     *
     * @param string $logMessage
     * @return void
     */
    private static function writeToLogFile(string $logMessage): void
    {
        // 尝试多个可能的日志目录路径
        $possibleLogDirs = [
            __DIR__ . '/../../../storage/logs',      // 标准 Laravel 路径
            __DIR__ . '/../../../../storage/logs',   // 备用路径
            __DIR__ . '/../../logs',                 // 包内路径
            sys_get_temp_dir() . '/trace-logs',      // 系统临时目录
        ];

        foreach ($possibleLogDirs as $logDir) {
            try {
                if (!is_dir($logDir)) {
                    @mkdir($logDir, 0755, true);
                }
                
                if (is_dir($logDir) && is_writable($logDir)) {
                    $logFile = $logDir . '/emergency-' . date('Y-m-d') . '.log';
                    $result = @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
                    if ($result !== false) {
                        return; // 成功写入，退出
                    }
                }
            } catch (\Throwable $e) {
                // 继续尝试下一个目录
                continue;
            }
        }
    }
}
