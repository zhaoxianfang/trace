<?php

namespace zxf\Trace\Traits;

use Exception;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * SQL 收集 Trait
 *
 * 负责：
 * 1. 监听和记录 SQL 查询
 * 2. 监听和记录事务操作
 * 3. SQL 参数替换与格式化
 * 4. SQL 分组归类
 */
trait SqlCollectorTrait
{
    /**
     * 监听 SQL 事件
     *
     * 注意：使用静态标记确保事件监听器只注册一次，避免重复监听
     *
     * @return void
     */
    protected function listenSql(): void
    {
        // 检查是否已经注册过事件监听器
        if (self::$eventListenersRegistered) {
            return;
        }

        $events = $this->app['events'] ?? null;
        if (! $events) {
            return;
        }

        try {
            // 监听SQL执行
            $events->listen(function (QueryExecuted $query) {
                // 只在当前请求 ID 匹配时才记录
                if (self::$currentRequestId === $this->requestId) {
                    $this->addQuery($query);
                }
            });
        } catch (Exception $e) {
            if (config('app.debug', false)) {
                error_log('[Trace] SQL监听错误: ' . $e->getMessage());
            }
        }

        try {
            // 监听事务相关事件
            $this->registerTransactionListeners($events);
        } catch (Exception $e) {
            if (config('app.debug', false)) {
                error_log('[Trace] 事务监听错误: ' . $e->getMessage());
            }
        }

        // 标记事件监听器已注册
        self::$eventListenersRegistered = true;
    }

    /**
     * 注册事务相关事件监听器
     *
     * @param mixed $events 事件调度器
     * @return void
     */
    protected function registerTransactionListeners($events): void
    {
        // 监听事务开始
        $events->listen(\Illuminate\Database\Events\TransactionBeginning::class, function ($transaction) {
            if (self::$currentRequestId === $this->requestId) {
                $this->addTransactionQuery('Begin Transaction', $transaction->connection);
            }
        });

        // 监听事务提交
        $events->listen(\Illuminate\Database\Events\TransactionCommitted::class, function ($transaction) {
            if (self::$currentRequestId === $this->requestId) {
                $this->addTransactionQuery('Commit Transaction', $transaction->connection);
            }
        });

        // 监听事务回滚
        $events->listen(\Illuminate\Database\Events\TransactionRolledBack::class, function ($transaction) {
            if (self::$currentRequestId === $this->requestId) {
                $this->addTransactionQuery('Rollback Transaction', $transaction->connection);
            }
        });

        // 连接事件映射
        $connectionEvents = [
            'beganTransaction' => 'Begin Transaction',
            'committed' => 'Commit Transaction',
            'rollingBack' => 'Rollback Transaction',
        ];

        foreach ($connectionEvents as $event => $eventName) {
            $events->listen('connection.*.' . $event, function ($event, $params) use ($eventName) {
                if (self::$currentRequestId === $this->requestId) {
                    $this->addTransactionQuery($eventName, $params[0] ?? null);
                }
            });
        }

        // 监听连接创建
        $events->listen(\Illuminate\Database\Events\ConnectionEstablished::class, function ($event) {
            if (self::$currentRequestId === $this->requestId) {
                $this->addTransactionQuery('Connection Established', $event->connection);
            }
        });
    }

    /**
     * 记录 SQL
     *
     * @param  QueryExecuted  $event
     */
    private function addQuery(QueryExecuted $event): void
    {
        try {
            // 内存自保护：内存临界时停止采集，避免本包引发 OOM
            if ($this->isMemoryCritical()) {
                return;
            }

            // 检查 SQL 列表大小限制（保留最新记录）
            if (count($this->sqlList) >= $this->maxSqlListSize) {
                array_shift($this->sqlList);
            }

            $bindings = $event->bindings ?? [];
            $sql = $event->sql ?? '';

            if (empty($sql)) {
                return;
            }

            // 限制最大绑定参数数量
            if (count($bindings) > $this->maxBindings) {
                $bindings = array_slice($bindings, 0, $this->maxBindings);
            }

            $sql = $this->replaceBindings($sql, $bindings);

            // 限制SQL长度
            if (strlen($sql) > $this->maxSqlLength) {
                $sql = substr($sql, 0, $this->maxSqlLength) . '... [TRUNCATED]';
            }

            $this->sqlList[] = [
                'sql' => $sql,
                'type' => 'Query',
                'time' => $event->time ?? 0,
            ];
        } catch (\Throwable $e) {
            if (config('app.debug', false)) {
                error_log('[Trace] SQL记录错误: ' . $e->getMessage());
            }
        }
    }

    /**
     * 替换 SQL 中的参数占位符
     *
     * @param  string  $sql SQL 语句
     * @param  array  $bindings 绑定的参数
     * @return string 替换后的 SQL
     */
    private function replaceBindings(string $sql, array $bindings): string
    {
        $maxBindings = 1000;
        $bindingCount = count($bindings);

        if ($bindingCount > $maxBindings) {
            $bindings = array_slice($bindings, 0, $maxBindings);
            $bindingCount = $maxBindings;
        }

        $offset = 0;
        $result = $sql;

        for ($i = 0; $i < $bindingCount; $i++) {
            $pos = strpos($result, '?', $offset);
            if ($pos === false) {
                break;
            }
            $bindingValue = $this->convertBindingToString($bindings[$i]);
            $result = substr_replace($result, $bindingValue, $pos, 1);
            $offset = $pos + strlen($bindingValue);
        }

        return $result;
    }

    /**
     * 将绑定的参数值转换为 SQL 字符串
     *
     * @param  mixed  $binding
     * @return string
     */
    private function convertBindingToString($binding): string
    {
        if (is_null($binding)) {
            return 'NULL';
        }

        if (is_bool($binding)) {
            return $binding ? 'true' : 'false';
        }

        if (is_string($binding)) {
            if (strlen($binding) > 1000) {
                $binding = substr($binding, 0, 1000) . '... [TRUNCATED]';
            }
            try {
                $pdo = DB::getPdo();
                if ($pdo) {
                    return $pdo->quote($binding);
                }
            } catch (\Throwable $e) {
                // PDO不可用时的回退方案
            }
            $binding = str_replace("'", "''", $binding);
            return "'{$binding}'";
        }

        if (is_numeric($binding)) {
            return (string) $binding;
        }

        if (is_array($binding)) {
            return json_encode($binding, JSON_UNESCAPED_UNICODE);
        }

        if (is_object($binding)) {
            if (method_exists($binding, '__toString')) {
                return "'" . str_replace("'", "''", (string) $binding) . "'";
            }
            return "'" . get_class($binding) . "'";
        }

        return "'" . str_replace("'", "''", (string) $binding) . "'";
    }

    /**
     * 记录事务 SQL
     *
     * @param  string  $event
     * @param  \Illuminate\Database\Connection  $connection
     * @return void
     */
    private function addTransactionQuery(string $event, $connection): void
    {
        try {
            if (! $connection) {
                return;
            }

            // 内存/数量自保护：避免事务事件在无上限情况下累积导致 OOM
            if ($this->isMemoryCritical() || count($this->sqlList) >= $this->maxSqlListSize) {
                return;
            }

            $connectionName = method_exists($connection, 'getName') ? $connection->getName() : 'unknown';
            $driver = method_exists($connection, 'getConfig') ? ($connection->getConfig('driver') ?? 'unknown') : 'unknown';

            $this->sqlList[] = [
                'sql' => '[' . $connectionName . ':' . $driver . '] ' . $event,
                'type' => 'Transaction',
                'time' => 0,
            ];
        } catch (\Throwable $e) {
            if (config('app.debug', false)) {
                error_log('[Trace] 事务查询错误: ' . $e->getMessage());
            }
        }
    }

    /**
     * 获取 SQL 信息列表（含分组）
     *
     * @return array [sqlList, sqlTimes]
     */
    private function getSqlInfo(): array
    {
        $sqlTimes = 0;
        $sqlList = [];

        $groupConfig = config('trace.sql_groups', ['enabled' => true, 'groups' => []]);

        $defaultGroupPatterns = [
            'cache' => [
                'name' => '缓存查询',
                'class' => 'sql-group-cache',
                'patterns' => [
                    '/select\s+.*\s+from\s+`?cache`?/i',
                    '/select\s+.*\s+from\s+`?cache_[\w]+`?/i',
                    '/insert\s+into\s+`?cache`?/i',
                    '/update\s+`?cache`?\s+set/i',
                    '/delete\s+from\s+`?cache`?/i',
                    '/select.*cache_key/i',
                    '/select.*cache_value/i',
                ],
            ],
            'session' => [
                'name' => '会话查询',
                'class' => 'sql-group-session',
                'patterns' => [
                    '/select\s+.*\s+from\s+`?sessions`?/i',
                    '/insert\s+into\s+`?sessions`?/i',
                    '/update\s+`?sessions`?\s+set/i',
                    '/delete\s+from\s+`?sessions`?/i',
                    '/select.*session_id/i',
                    '/select.*user_id/i',
                    '/select.*payload/i',
                ],
            ],
        ];

        $groupPatterns = array_merge($defaultGroupPatterns, $groupConfig['groups'] ?? []);
        $groupEnabled = $groupConfig['enabled'] ?? true;
        $collapsedByDefault = $groupConfig['collapsed_by_default'] ?? false;

        $groupedSql = array_fill_keys(array_keys($groupPatterns), []);
        $groupedSql['other'] = [];

        foreach ($this->sqlList as $item) {
            if (! isset($item['time'])) {
                $sqlList[] = [
                    'label' => $item['sql'],
                    'right' => '-',
                ];
                continue;
            }

            $sqlTimes += $item['time'];
            $sqlItem = [
                'label' => $item['sql'],
                'right' => !empty($item['time']) ? $item['time'] . 'ms' : '-',
            ];

            if ($groupEnabled) {
                $matchedGroup = null;
                foreach ($groupPatterns as $groupKey => $group) {
                    foreach ($group['patterns'] as $pattern) {
                        if (preg_match($pattern, $item['sql'])) {
                            $matchedGroup = $groupKey;
                            break 2;
                        }
                    }
                }

                if ($matchedGroup && isset($groupedSql[$matchedGroup])) {
                    $groupedSql[$matchedGroup][] = $sqlItem;
                } else {
                    $groupedSql['other'][] = $sqlItem;
                }
            } else {
                $sqlList[] = $sqlItem;
            }
        }

        if ($groupEnabled) {
            $hasGroups = false;
            foreach (array_keys($groupPatterns) as $groupKey) {
                if (!empty($groupedSql[$groupKey])) {
                    $hasGroups = true;
                    break;
                }
            }

            if ($hasGroups) {
                if (!empty($groupedSql['other'])) {
                    $sqlList = $groupedSql['other'];
                }

                foreach ($groupPatterns as $groupKey => $group) {
                    if (!empty($groupedSql[$groupKey])) {
                        $sqlList[] = [
                            'type' => 'sql_group',
                            'group' => $groupKey,
                            'name' => $group['name'] ?? $groupKey,
                            'class' => $group['class'] ?? 'sql-group',
                            'collapsed' => $collapsedByDefault,
                            'sqls' => $groupedSql[$groupKey],
                            'count' => count($groupedSql[$groupKey]),
                        ];
                    }
                }
            } else {
                $sqlList = $groupedSql['other'];
            }
        }

        $sqlTimes = $sqlTimes > 0 ? round($sqlTimes / 1000, 3) : 0;

        return [$sqlList, $sqlTimes];
    }
}
