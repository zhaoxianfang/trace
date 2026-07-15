<?php

/**
 * Trace 包 Opcache 预加载文件
 *
 * 使用方法：
 * 在 php.ini 中添加：
 * opcache.preload=/path/to/vendor/zxf/trace/preload.php
 * opcache.preload_user=www-data
 *
 * 此文件用于在 PHP-FPM 启动时预加载 Trace 包的核心文件到内存中，
 * 可以显著减少请求响应时间和内存使用。
 */

if (!function_exists('opcache_compile_file')) {
    return;
}

// 获取包目录
$traceDir = __DIR__ . '/src';

// 核心文件列表 - 按加载顺序排列
$coreFiles = [
    // 基础辅助函数
    $traceDir . '/helpers.php',

    // Trait 文件（按依赖顺序）
    $traceDir . '/Trace/Traits/ExceptionCodeTrait.php',
    $traceDir . '/Trace/Traits/ExceptionNotifyTrait.php',
    $traceDir . '/Trace/Traits/ExceptionShowDebugHtmlTrait.php',
    $traceDir . '/Trace/Traits/ExceptionCustomCallbackTrait.php',
    $traceDir . '/Trace/Traits/ExceptionTrait.php',
    $traceDir . '/Trace/Traits/AppEndTrait.php',
    $traceDir . '/Trace/Traits/TraceResponseTrait.php',

    // 核心类
    $traceDir . '/Trace/Handle.php',
    $traceDir . '/Trace/TraceExceptionHandler.php',
    $traceDir . '/Trace/CustomExceptionHandler.php',
    $traceDir . '/Trace/PerformanceMonitor.php',
    $traceDir . '/Trace/AssetController.php',

    // 中间件和服务提供者
    $traceDir . '/Trace/Middleware/TraceMiddleware.php',
    $traceDir . '/Trace/Providers/TraceServiceProvider.php',
    $traceDir . '/Trace/Providers/RouteServiceProvider.php',

    // 路由
    $traceDir . '/Trace/routes/trace.php',
];

// 编译统计
$compiled = 0;
$failed = 0;

foreach ($coreFiles as $file) {
    if (file_exists($file)) {
        try {
            opcache_compile_file($file);
            $compiled++;
        } catch (Throwable $e) {
            $failed++;
            error_log("[Trace Preload] Failed to compile: {$file} - {$e->getMessage()}");
        }
    }
}

// 记录预加载结果（仅在调试模式）
$preloadDebug = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? false;
$preloadDebug = is_string($preloadDebug)
    ? in_array(strtolower($preloadDebug), ['true', '1', 'yes', 'on'], true)
    : (bool) $preloadDebug;
if ($preloadDebug) {
    error_log("[Trace Preload] Compiled: {$compiled}, Failed: {$failed}");
}
