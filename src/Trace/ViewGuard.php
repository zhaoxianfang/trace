<?php

namespace zxf\Trace;

/**
 * Trace 视图保护器
 *
 * 用途：确保 Trace 扩展包的视图只能被内部调用
 * 防止外部代码直接访问 Trace 的私有视图
 */
class ViewGuard
{
    /**
     * 允许访问的视图列表
     */
    private static array $allowedViews = [
        'trace::error',
        'trace::debug',
    ];

    /**
     * 内部专用视图列表（仅限 Trace 包内部使用）
     */
    private static array $internalViews = [
        'trace::panel',
    ];

    /**
     * 调用栈中必须包含的命名空间（用于验证内部调用）
     */
    private static array $internalNamespaces = [
        'zxf\Trace',
    ];

    /**
     * 检查是否可以访问指定视图
     *
     * @param string $viewName 视图名称
     * @return bool
     */
    public static function canAccess(string $viewName): bool
    {
        // 公开视图始终允许访问
        if (in_array($viewName, self::$allowedViews, true)) {
            return true;
        }

        // 内部视图需要验证调用来源（必须来自 zxf\Trace 命名空间）
        if (in_array($viewName, self::$internalViews, true)) {
            return self::isInternalCall();
        }

        // 允许 trace::errors.* 系列（兜底异常处理器可能引用，视图可由发布/自定义提供）
        if (str_starts_with($viewName, 'trace::errors.')) {
            return true;
        }

        // 默认拒绝：其他 trace:: 视图一律禁止外部直接访问，防止内部视图泄露
        return false;
    }

    /**
     * 检查是否为内部调用
     *
     * 通过检查调用栈来判断是否来自 Trace 包内部
     *
     * @return bool
     */
    public static function isInternalCall(): bool
    {
        // 获取调用栈
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);

        // 遍历调用栈，检查是否有来自 zxf\Trace 命名空间的调用
        foreach ($trace as $frame) {
            if (isset($frame['class'])) {
                foreach (self::$internalNamespaces as $namespace) {
                    if (str_starts_with($frame['class'], $namespace)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * 获取允许访问的视图列表
     *
     * @return array
     */
    public static function getAllowedViews(): array
    {
        return self::$allowedViews;
    }

    /**
     * 获取内部专用视图列表
     *
     * @return array
     */
    public static function getInternalViews(): array
    {
        return self::$internalViews;
    }

    /**
     * 注册视图保护
     *
     * 在 ServiceProvider 中调用，拦截非法的视图访问
     *
     * @return void
     */
    public static function register(): void
    {
        try {
            if (!function_exists('app') || !app()->bound('view')) {
                return;
            }

            $view = app('view');

            // 监听视图创建事件
            $view->creator('*', function ($view) {
                $viewName = $view->getName();

                // 检查是否是 Trace 视图
                if (str_starts_with($viewName, 'trace::')) {
                    if (!self::canAccess($viewName)) {
                        // 阻止访问内部视图
                        throw new \RuntimeException(
                            "Access denied: View '{$viewName}' is for internal use only."
                        );
                    }
                }
            });
        } catch (\Throwable $e) {
            // 静默处理，不影响正常流程
            error_log('[Trace] ViewGuard registration failed: ' . $e->getMessage());
        }
    }
}
