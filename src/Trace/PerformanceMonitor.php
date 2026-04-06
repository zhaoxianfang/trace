<?php

namespace zxf\Trace;

/**
 * Trace 性能监控器
 *
 * 用于监控和记录 Trace 包本身的性能指标，包括：
 * - 内存使用情况
 * - 执行时间
 * - SQL 查询数量
 * - 缓存命中率
 *
 * 使用示例：
 * PerformanceMonitor::start('operation_name');
 * // ... 执行操作 ...
 * $metrics = PerformanceMonitor::end('operation_name');
 */
class PerformanceMonitor
{
    /**
     * 性能指标存储
     */
    protected static array $metrics = [];

    /**
     * 活跃的操作计时器
     */
    protected static array $timers = [];

    /**
     * 缓存命中统计
     */
    protected static array $cacheStats = [
        'hits' => 0,
        'misses' => 0,
    ];

    /**
     * 开始计时
     *
     * @param string $name 操作名称
     * @param array $metadata 额外元数据
     * @return void
     */
    public static function start(string $name, array $metadata = []): void
    {
        self::$timers[$name] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'metadata' => $metadata,
        ];
    }

    /**
     * 结束计时并返回性能指标
     *
     * @param string $name 操作名称
     * @return array 性能指标数据
     */
    public static function end(string $name): array
    {
        if (!isset(self::$timers[$name])) {
            return [];
        }

        $timer = self::$timers[$name];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $metrics = [
            'name' => $name,
            'duration_ms' => round(($endTime - $timer['start_time']) * 1000, 3),
            'memory_delta' => $endMemory - $timer['start_memory'],
            'memory_peak' => memory_get_peak_usage(true),
            'timestamp' => $endTime,
        ];

        if (!empty($timer['metadata'])) {
            $metrics['metadata'] = $timer['metadata'];
        }

        // 存储指标
        self::$metrics[$name][] = $metrics;

        // 限制存储数量防止内存泄漏
        if (count(self::$metrics[$name]) > 100) {
            array_shift(self::$metrics[$name]);
        }

        unset(self::$timers[$name]);

        return $metrics;
    }

    /**
     * 记录缓存命中
     *
     * @return void
     */
    public static function recordCacheHit(): void
    {
        self::$cacheStats['hits']++;
    }

    /**
     * 记录缓存未命中
     *
     * @return void
     */
    public static function recordCacheMiss(): void
    {
        self::$cacheStats['misses']++;
    }

    /**
     * 获取缓存命中率
     *
     * @return float 命中率（0-100）
     */
    public static function getCacheHitRate(): float
    {
        $total = self::$cacheStats['hits'] + self::$cacheStats['misses'];
        if ($total === 0) {
            return 0.0;
        }
        return round((self::$cacheStats['hits'] / $total) * 100, 2);
    }

    /**
     * 获取所有性能指标
     *
     * @return array
     */
    public static function getAllMetrics(): array
    {
        return [
            'metrics' => self::$metrics,
            'cache_stats' => self::$cacheStats,
            'cache_hit_rate' => self::getCacheHitRate(),
            'active_timers' => count(self::$timers),
        ];
    }

    /**
     * 获取特定操作的指标
     *
     * @param string $name 操作名称
     * @return array
     */
    public static function getMetrics(string $name): array
    {
        return self::$metrics[$name] ?? [];
    }

    /**
     * 清除所有指标
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$metrics = [];
        self::$timers = [];
        self::$cacheStats = ['hits' => 0, 'misses' => 0];
    }

    /**
     * 获取性能摘要
     *
     * @return array
     */
    public static function getSummary(): array
    {
        $summary = [
            'total_operations' => 0,
            'total_duration_ms' => 0,
            'avg_duration_ms' => 0,
            'cache_hit_rate' => self::getCacheHitRate(),
        ];

        foreach (self::$metrics as $name => $metrics) {
            $summary['total_operations'] += count($metrics);
            foreach ($metrics as $metric) {
                $summary['total_duration_ms'] += $metric['duration_ms'];
            }
        }

        if ($summary['total_operations'] > 0) {
            $summary['avg_duration_ms'] = round(
                $summary['total_duration_ms'] / $summary['total_operations'],
                3
            );
        }

        return $summary;
    }
}
