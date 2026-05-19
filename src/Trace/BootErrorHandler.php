<?php

namespace zxf\Trace;

/**
 * BootErrorHandler — 引导阶段错误拦截补充类
 *
 * 核心拦截逻辑在 bootstrap.php 的内联处理器中完成（零外部依赖）。
 * 本类仅作为补充，当 Laravel 可用时使用 Blade 视图渲染更漂亮的错误页。
 */
class BootErrorHandler
{
    private static bool $initialized = false;

    /**
     * 从 bootstrap.php 接收拦截状态
     */
    public static function initFromBootstrap(string $executionId, ?array &$lastError, bool &$criticalDetected, bool &$isChild): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        // 注册兜底 shutdown（在 bootstrap 的 shutdown 之后执行）
        register_shutdown_function(function () use ($executionId, &$lastError, &$criticalDetected, &$isChild) {
            if (headers_sent()) {
                return;
            }
            $error = error_get_last();
            if ($error !== null && _trace_is_fatal($error['type'])) {
                self::tryBladeRender(
                    match ($error['type']) { E_ERROR => 507, default => 500 },
                    _trace_err_name($error['type']),
                    $error['message'] ?? '',
                    $error['file'] ?? '',
                    $error['line'] ?? 0,
                    $executionId
                );
            }
        });
    }

    /**
     * 获取当前拦截状态（供其他组件使用）
     */
    public static function hasIntercepted(): bool
    {
        return self::$initialized && !headers_sent();
    }

    /**
     * 尝试用 Blade 视图渲染
     */
    private static function tryBladeRender(int $code, string $title, string $message, string $file, int $line, string $executionId): void
    {
        // 临时关闭 PHP 错误显示，防止 Blade 渲染期间的弃用警告污染输出
        @ini_set('display_errors', '0');
        error_reporting(0);

        try {
            if (! function_exists('view') || ! function_exists('app')) {
                return;
            }
            $app = @app();
            if (! $app || ! $app->bound('view')) {
                return;
            }
            $view = $app->make('view');
            $vp = __DIR__ . '/../Resources/views';
            if (is_dir($vp)) {
                try {
                    $ns = $view->getFinder()->getHints();
                    if (! isset($ns['trace'])) {
                        $view->addNamespace('trace', $vp);
                    }
                } catch (\Throwable $e) {
                    $view->addNamespace('trace', $vp);
                }
            }
            $list = [
                ['label' => '错误类型', 'type' => 'string', 'value' => $title],
                ['label' => '错误信息', 'type' => 'string', 'value' => $message],
            ];
            if (! empty($file)) {
                $list[] = ['label' => '错误文件', 'type' => 'debug_file', 'value' => "{$file}:{$line}", 'file' => $file, 'line' => $line];
            }
            $list[] = ['label' => '状态码', 'type' => 'string', 'value' => $code];
            $list[] = ['label' => 'PHP版本', 'type' => 'string', 'value' => PHP_VERSION];
            $data = [
                'code'      => $code,
                'title'     => $title,
                'message'   => $message,
                'requestId' => $executionId,
                'timestamp' => date('Y-m-d H:i:s'),
                'isDebug'   => _trace_debug(),
                'list'      => $list,
            ];
            if ($view->exists('trace::error')) {
                _trace_clean_buf();
                echo $view->make('trace::error', $data)->render();
                return;
            }
            if ($view->exists('trace::debug')) {
                _trace_clean_buf();
                echo $view->make('trace::debug', $data)->render();
            }
        } catch (\Throwable $e) {
        }
    }
}
