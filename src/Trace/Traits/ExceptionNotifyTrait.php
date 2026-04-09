<?php

namespace zxf\Trace\Traits;

use JetBrains\PhpStorm\NoReturn;
use Throwable;

/**
 * 发送异常通知给管理员等 Trait
 */
trait ExceptionNotifyTrait
{
    #[NoReturn]
    public function showExitMessage(Throwable $e): void
    {
        $code = (int) (self::$code ?? 500);
        $rawMessage = self::$isSysErr ? $e->getMessage() : self::$message;
        $message = htmlspecialchars($rawMessage, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $debugInfo = '';

        if (config('app.debug')) {
            $errFile = htmlspecialchars(str_replace(base_path(), '', $e->getFile()).':'.$e->getLine().' (行)', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $debugInfo .= "<p>[异常提示]:</p>";
            $debugInfo .= "<p>➤ [异常文件]:{$errFile}</p>";

            // 匹配：Target class [admin] does not exist.
            if (preg_match('/Target class \[([a-z]+)\] does not exist\./', $rawMessage, $matches)) {
                $className = htmlspecialchars($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $debugInfo .= "<p>[调试提示]:</p>";
                $debugInfo .= "<p>➤ 请检查「{$className}」相关的类、中间件、路由是否存在；</p>";
                $debugInfo .= "<p>➤ 请检查「{$className}」相关的命名空间或字符串大小写等是否正确</p>";
            }
        }

        $title = config('app.name', '系统错误');

        // 尝试使用 Blade 视图
        try {
            if (function_exists('view') && app()->bound('view')) {
                $view = app('view');
                
                // 检查 fatal 视图是否存在
                if ($view->exists('trace::fatal')) {
                    $html = $view->make('trace::fatal', [
                        'code' => $code,
                        'message' => $message,
                        'debugInfo' => $debugInfo,
                        'title' => $title,
                    ])->render();
                    
                    exit($html);
                }
            }
        } catch (\Throwable $viewError) {
            // 视图渲染失败，降级到通用视图
        }

        // 尝试使用 emergency 视图
        try {
            if (function_exists('view') && app()->bound('view')) {
                $view = app('view');
                if ($view->exists('trace::emergency')) {
                    $html = $view->make('trace::emergency', [
                        'code' => $code,
                        'title' => $title,
                        'message' => $message,
                        'emoji' => '💥',
                        'showDebug' => config('app.debug', false),
                        'requestId' => $this->generateRequestId(),
                        'timestamp' => date('Y-m-d H:i:s'),
                    ])->render();
                    
                    exit($html);
                }
            }
        } catch (\Throwable $viewError) {
            // 视图渲染失败，降级到内置模板
        }

        // 最终兜底：直接输出
        $this->lastResortOutput($code, $message, $title);
    }

    /**
     * 生成请求 ID
     *
     * @return string
     */
    private function generateRequestId(): string
    {
        try {
            if (function_exists('request') && request()) {
                return request()->header('X-Request-ID', substr(md5(uniqid('', true)), 0, 12));
            }
        } catch (\Throwable $e) {
            // 忽略请求获取错误
        }
        return substr(md5(uniqid('', true)), 0, 12);
    }

    /**
     * 最后的输出方案（当所有视图都不可用时）
     *
     * @param int $code
     * @param string $message
     * @param string $title
     */
    #[NoReturn]
    private function lastResortOutput(int $code, string $message, string $title): void
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>出错啦|{$safeTitle}</title>
    <style>
        body{background:#f5f5f5;min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0;font-family:sans-serif}
        .box{background:#fff;padding:40px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);text-align:center;max-width:400px}
        h1{color:#e74c3c;font-size:48px;margin:0 0 20px}
        p{color:#666;margin:10px 0}
        a{color:#3498db;text-decoration:none}
        a:hover{text-decoration:underline}
    </style>
</head>
<body>
    <div class="box">
        <h1>{$code}</h1>
        <p><strong>{$safeTitle}</strong></p>
        <p>{$message}</p>
        <p><a href="/">返回首页</a></p>
    </div>
</body>
</html>
HTML;
        exit;
    }
}
