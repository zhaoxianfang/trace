<?php

namespace zxf\Trace;

use Closure;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use ParseError;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use Throwable;
use zxf\Trace\Traits\AppEndTrait;
use zxf\Trace\Traits\ExceptionCodeTrait;
use zxf\Trace\Traits\ExceptionCustomCallbackTrait;
use zxf\Trace\Traits\ExceptionShowDebugHtmlTrait;
use zxf\Trace\Traits\ExceptionTrait;
use zxf\Trace\Traits\TraceResponseTrait;

/**
 * Trace 调试处理器
 *
 * 核心功能:
 * - 监听和记录 SQL 查询（支持分组）
 * - 监听和记录模型事件
 * - 收集请求性能数据
 * - 提供调试信息输出
 *
 * 注意事项:
 * - 使用单例模式，但通过 requestId 进行请求级别的状态隔离
 * - 支持常驻内存环境（如 Octane、Swoole）
 *
 * @property-read int $startTime 请求开始时间（微秒时间戳）
 * @property-read int $startMemory 请求开始内存使用量（字节）
 * @property-read string $requestId 当前请求的唯一标识符
 * @property-read array $sqlList SQL查询列表
 * @property-read array $messages 调试消息列表
 * @property-read Throwable|null $currentException 当前异常对象
 *
 * @package zxf\Trace
 */
class Handle
{
    use AppEndTrait;
    use ExceptionCodeTrait;
    use ExceptionCustomCallbackTrait;
    use ExceptionShowDebugHtmlTrait;
    use ExceptionTrait;
    use TraceResponseTrait;

    /**
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * @var Router
     */
    protected $router;

    protected $startTime;

    protected $startMemory;

    protected array $config = [
        'tabs' => [
            'messages' => 'Messages',
            'base' => 'Base',
            'route' => 'Route',
            'view' => 'View',
            'models' => 'Models',
            'sql' => 'SQL',
            'exception' => 'Exception',
            'session' => 'Session',
            'request' => 'Request',
        ],
    ];

    protected array $sqlList = [];

    protected static array $modelList = [];

    // 请求 ID，用于区分不同请求
    protected ?string $requestId = null;

    protected array $messages = [];

    // 存储当前请求的异常信息
    protected ?Throwable $currentException = null;

    // 反射缓存 - 优化性能
    protected static array $reflectionCache = [];

    // 反射缓存最大条目数
    protected static int $maxReflectionCacheSize = 100;

    // SQL 列表最大条目数
    protected int $maxSqlListSize = 500;

    // 单条 SQL 最大长度
    protected int $maxSqlLength = 1500;

    // 最大绑定参数数量
    protected int $maxBindings = 50;

    /** @var Request */
    protected $request;

    /** @var Response */
    protected $response;

    // 全局标记：事件监听器是否已注册
    protected static bool $eventListenersRegistered = false;

    // 全局标记：当前正在处理的请求 ID
    protected static ?string $currentRequestId = null;

    // 请求计数器，用于追踪请求次数
    protected static int $requestCounter = 0;

    // 配置缓存，避免重复读取
    protected static array $configCache = [];

    // SQL 分组模式缓存
    protected static ?array $sqlGroupPatterns = null;

    // 是否启用 SQL 分组缓存
    protected static ?bool $sqlGroupingEnabled = null;

    /**
     * 实例化并初始化请求级别状态
     *
     * @param  Application|null  $app
     * @param  array  $config
     *
     * @throws BindingResolutionException
     */
    public function __construct(?Application $app = null, array $config = [])
    {
        // 生成唯一的请求 ID
        $this->requestId = uniqid('trace_', true);

        // 更新全局当前请求 ID
        self::$currentRequestId = $this->requestId;
        self::$requestCounter++;

        // 从配置读取限制值（使用静态属性避免重复读取）
        $this->loadConfigLimits();

        if (! is_enable_trace()) {
            return;
        }

        $this->startMemory = memory_get_usage();

        if (! $app) {
            $app = app();
        }
        $this->app = $app;
        $this->router = $this->app['router'];

        // 安全获取开始时间
        $this->startTime = $this->getStartTime();

        $this->request = $app['request'];
        $this->config = array_merge($this->config, $config);

        $this->listenModelEvent();
        $this->listenSql();
    }

    /**
     * 加载配置中的限制值
     *
     * @return void
     */
    protected function loadConfigLimits(): void
    {
        if (! isset(self::$configCache['limits'])) {
            self::$configCache['limits'] = config('trace.limits', []);
        }

        $limits = self::$configCache['limits'];
        $this->maxSqlListSize = $limits['max_sql_count'] ?? 500;
        $this->maxSqlLength = $limits['max_sql_length'] ?? 1500;
        $this->maxBindings = $limits['max_bindings'] ?? 50;
        self::$maxReflectionCacheSize = $limits['max_reflection_cache'] ?? 100;
    }

    /**
     * 获取缓存的配置值
     *
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @return mixed
     */
    protected static function getCachedConfig(string $key, mixed $default = null): mixed
    {
        if (! array_key_exists($key, self::$configCache)) {
            self::$configCache[$key] = config($key, $default);
        }
        return self::$configCache[$key];
    }

    /**
     * 清除配置缓存（用于配置变更时）
     *
     * @return void
     */
    public static function clearConfigCache(): void
    {
        self::$configCache = [];
        self::$sqlGroupPatterns = null;
        self::$sqlGroupingEnabled = null;
    }

    /**
     * 获取当前请求ID
     *
     * @return string|null
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * 获取请求开始时间
     *
     * @return float
     */
    protected function getStartTime(): float
    {
        // 尝试从服务器变量获取
        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            return $_SERVER['REQUEST_TIME_FLOAT'];
        }

        // 尝试从应用容器获取
        if (isset($this->app['request'])) {
            $time = $this->app['request']->server('REQUEST_TIME_FLOAT');
            if ($time !== null) {
                return (float) $time;
            }
        }

        // 回退到 LARAVEL_START 常量
        if (defined('LARAVEL_START')) {
            return LARAVEL_START;
        }

        // 最后回退到当前时间
        return microtime(true);
    }

    /**
     * 监听模型事件
     *
     * 注意：使用静态标记确保事件监听器只注册一次，避免重复监听
     */
    public function listenModelEvent(): void
    {
        // 检查是否已经注册过事件监听器
        if (self::$eventListenersRegistered) {
            return;
        }

        $events = ['retrieved', 'creating', 'created', 'updating', 'updated', 'saving', 'saved', 'deleting', 'deleted', 'restoring', 'restored', 'replicating'];

        foreach ($events as $event) {
            Event::listen('eloquent.'.$event.':*', function ($listenString, $model) use ($event) {
                // 只在当前请求 ID 匹配时才记录
                if (self::$currentRequestId === $this->requestId) {
                    $this->logModelEvent($listenString, $model, $event);
                }
            });
        }
    }

    /**
     * 监听 SQL事件
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
            $events->listen('connection.*.'.$event, function ($event, $params) use ($eventName) {
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
     * 记录sql
     *
     * @param  QueryExecuted  $event
     */
    private function addQuery(QueryExecuted $event): void
    {
        try {
            // 检查 SQL 列表大小限制
            if (count($this->sqlList) >= $this->maxSqlListSize) {
                // 如果超过限制，移除最早的条目
                array_shift($this->sqlList);
            }

            // 获取绑定的参数
            $bindings = $event->bindings ?? [];
            $sql = $event->sql ?? '';

            // 安全检查：确保 SQL 不为空
            if (empty($sql)) {
                return;
            }

            // 限制最大绑定参数数量，防止性能问题
            if (count($bindings) > $this->maxBindings) {
                $bindings = array_slice($bindings, 0, $this->maxBindings);
            }

            // 替换参数占位符 - 使用性能更好的方法
            $sql = $this->replaceBindings($sql, $bindings);

            // 限制SQL长度，避免内存溢出
            if (strlen($sql) > $this->maxSqlLength) {
                $sql = substr($sql, 0, $this->maxSqlLength) . '... [TRUNCATED]';
            }

            $this->sqlList[] = [
                'sql' => $sql,
                'type' => 'Query',
                'time' => $event->time ?? 0, // 'ms'
            ];
        } catch (\Throwable $e) {
            if (config('app.debug', false)) {
                error_log('[Trace] SQL记录错误: ' . $e->getMessage());
            }
        }
    }

    /**
     * 替换 SQL 中的参数占位符
     * 使用 strpos + substr_replace 代替 preg_replace，提升性能
     *
     * 安全说明：
     * 1. 本方法仅用于调试展示，不执行实际查询
     * 2. 使用 PDO quote 进行参数转义
     * 3. 限制字符串长度防止内存溢出
     *
     * @param  string  $sql SQL 语句
     * @param  array  $bindings 绑定的参数
     * @return string 替换后的 SQL
     */
    private function replaceBindings(string $sql, array $bindings): string
    {
        // 限制最大绑定参数数量，防止性能问题（使用配置值或默认1000）
        $maxBindings = 1000;
        $bindingCount = count($bindings);
        
        if ($bindingCount > $maxBindings) {
            $bindings = array_slice($bindings, 0, $maxBindings);
            $bindingCount = $maxBindings;
        }

        // 性能优化：预分配字符串缓冲区，减少内存重新分配
        $offset = 0;
        $result = $sql;
        
        for ($i = 0; $i < $bindingCount; $i++) {
            $pos = strpos($result, '?', $offset);
            if ($pos === false) {
                break;
            }
            $bindingValue = $this->convertBindingToString($bindings[$i]);
            $result = substr_replace($result, $bindingValue, $pos, 1);
            // 更新偏移量，避免重复查找已替换的部分
            $offset = $pos + strlen($bindingValue);
        }
        
        return $result;
    }

    /**
     * 将绑定的参数值转换为SQL字符串
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
            // 限制字符串长度防止内存溢出
            if (strlen($binding) > 1000) {
                $binding = substr($binding, 0, 1000) . '... [TRUNCATED]';
            }
            // 使用PDO的quote方法进行更安全的转义
            try {
                $pdo = DB::getPdo();
                if ($pdo) {
                    return $pdo->quote($binding);
                }
            } catch (\Throwable $e) {
                // PDO不可用时的回退方案
            }
            // 回退到基本转义
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

        // 其他类型转换为字符串并转义
        return "'" . str_replace("'", "''", (string) $binding) . "'";
    }

    /**
     * 记录事务sql
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

            $connectionName = method_exists($connection, 'getName') ? $connection->getName() : 'unknown';
            $driver = method_exists($connection, 'getConfig') ? ($connection->getConfig('driver') ?? 'unknown') : 'unknown';

            $this->sqlList[] = [
                'sql' => '['.$connectionName.':'.$driver.'] '.$event,
                'type' => 'Transaction',
                'time' => 0,
                //                 'connection' => $connection->getName(),
                // 'driver'     => $connection->getConfig('driver'),
            ];
        } catch (\Throwable $e) {
            if (config('app.debug', false)) {
                error_log('[Trace] 事务查询错误: ' . $e->getMessage());
            }
        }
    }

    /**
     * 记录模型事件
     *
     * 注意：使用请求 ID 进行数据隔离，防止不同请求的数据混淆
     *
     * @param  string  $listenString  监听字符串（如 "eloquent.retrieved:App\Models\User"）
     * @param  mixed  $model  模型实例或模型数组
     * @param  string  $event  事件名称（如 "retrieved", "created"）
     */
    protected function logModelEvent($listenString, $model, $event): void
    {
        // 检查当前请求 ID 是否匹配，防止跨请求事件混入
        if (self::$currentRequestId !== $this->requestId) {
            return;
        }

        $model = is_array($model) && isset($model[0]) ? $model[0] : $model;

        // 使用: 分割 $model , 获取模型名称
        $modelName = trim(explode(':', $listenString)[1]);

        $modelId = $model->getKey();

        // 使用请求 ID 作为键，避免不同请求的数据混淆
        self::$modelList[$this->requestId] ??= [];
        self::$modelList[$this->requestId][] = [
            'model' => $modelName,
            'id' => $modelId,
            'event' => $event,
        ];
    }

    /**
     * 输出 Trace 调试信息
     *
     * 注意：添加请求级别检查，确保只处理当前请求的数据
     *
     * @param  Response  $response  HTTP 响应对象
     * @return string Trace 调试 HTML 内容（如果需要渲染）
     */
    public function output($response): string
    {
        if (! is_enable_trace()) {
            // 运行在命令行下
            return '';
        }

        // 检查当前请求 ID 是否匹配
        if (self::$currentRequestId !== $this->requestId) {
            return '';
        }

        $this->response = $response;

        $exception = [];
        $hasParseError = false; // 判断是否有语法错误

        // 获取异常对象：优先从当前实例获取，其次从响应对象获取，最后检查静态属性
        $exceptionObj = null;

        // 首先检查 Handle 实例中存储的异常（通过 initError 方法设置）
        if ($this->currentException instanceof Throwable) {
            $exceptionObj = $this->currentException;
        }
        // 其次检查响应对象中的异常属性
        elseif (property_exists($response, 'exception') && ! empty($response->exception)) {
            $exceptionObj = $response->exception;
        }
        // 最后检查 ExceptionTrait 静态属性（兼容性回退）
        elseif (self::$initErr && ! empty(self::$message)) {
            // 从 ExceptionTrait 的静态属性中重建异常信息
            // 注意：这里我们无法获取完整的异常对象，只能获取已处理的信息
            $fileName = self::$content['file:'] ?? '';
            $line = self::$content['line:'] ?? 0;
            $code = self::$content['code:'] ?? 500;
            $message = self::$message;

            $exception = [
                'message' => $message,
                'line' => $line,
                'exception' => '<pre class="show" style="line-height: 14px;"><code>'.$this->getExceptionContent(self::$errObj).'</code></pre>',
                'file' => '<span class="json-label"><a href="'.(config('trace.editor') ?? 'phpstorm').'://open?file='.urlencode($fileName).'&amp;line='.$line.'" class="phpdebugbar-link">'.($fileName.'#'.$line).'</a></span>',
                'code' => $code,
            ];
        }

        // 如果有异常对象，则构建异常信息数组
        if ($exceptionObj instanceof Throwable) {
            $hasParseError = $exceptionObj instanceof ParseError; // 判断是否有语法错误
            $exceptionString = $this->getExceptionContent($exceptionObj);
            $fileName = $this->getFilePath($exceptionObj->getFile());
            $editor = config('trace.editor') ?? 'phpstorm';
            $exception = [
                'message' => $exceptionObj->getMessage(),
                'line' => $exceptionObj->getLine(),
                'exception' => '<pre class="show" style="line-height: 14px;"><code>'.$exceptionString.'</code></pre>',
                'file' => '<span class="json-label"><a href="'.$editor.'://open?file='.urlencode($exceptionObj->getFile()).'&amp;line='.$exceptionObj->getLine().'" class="phpdebugbar-link">'.($fileName.'#'.$exceptionObj->getLine()).'</a></span>',
                'code' => $exceptionObj->getCode(),
            ];
        }

        [$sql, $sqlTimes] = $this->getSqlInfo();
        $messages = $this->messages;
        $base = $this->getBaseInfo($sqlTimes);
        $route = $this->getRouteInfo($hasParseError);
        $session = $this->getSessionInfo();
        $request = $this->getRequestInfo();
        $view = $this->getViewInfo();
        $models = $this->getModelList();

        // 页面Trace信息
        $trace = [];
        foreach ($this->config['tabs'] as $name => $title) {
            $name = strtolower($name);
            $result = [];
            foreach ($$name as $subTitle => $item) {
                $result[$subTitle] = $item;
            }
            // 显示数字提示
            $showTips = in_array($name, ['messages', 'sql', 'models']) && ! empty($result) ? ' ('.count($result).')' : '';
            $showTips = in_array($name, ['exception']) && ! empty($result) ? ' 🔴' : $showTips;

            $trace[$title.$showTips] = ! empty($result) ? $result : $this->getEmptyTips($name);
        }

        try {
            // 自定义处理
            $this->traceEndHandle($trace);
        } catch (Exception $e) {
            return '';
        }

        // 不是ajax请求的GET请求 && 不是生产环境 的直接在页面渲染
        // 注意：使用 $this->request 而不是 request()，避免全局 request 可能不可用
        if ($this->request->isMethod('get') && ! $this->request->expectsJson() && ! ($response instanceof \Illuminate\Http\JsonResponse) && ! $this->app->environment('production')) {
            return $this->randerPage($trace);
        }

        return '';
    }

    /**
     * 获取空状态下的tab提示信息
     *
     * @param string|null $tabName 标签名称，如 'messages', 'sql', 'view', 'exception' 等
     * @return array 包含提示信息的数组，格式: ['提示消息', '额外提示']
     *
     * @throws \InvalidArgumentException 当标签名称无效时抛出（可选）
     */
    private function getEmptyTips(?string $tabName = ''): array
    {
        [$message, $tips] = match (strtolower($tabName)) {
            'messages' => ['暂无调试内容', '使用 trace(mixed ...$args) 函数进行调试'],
            'sql' => ['暂无sql查询', ''],
            'view' => ['没有加载视图', ''],
            'exception' => ['暂无异常信息', ''],
            default => ['暂无内容', ''],
        };
        return [$message.(!empty($tips)? ' <span style="font-size: 12px;color: #aaa;">提示: '.$tips.'</span>' : '') ];
    }

    /**
     * 获取模型列表并清理数据
     *
     * 注意：在获取数据后立即清理当前请求的模型数据，避免内存累积
     *
     * @return array 模型使用统计列表
     */
    private function getModelList(): array
    {
        $data = [];

        // 只获取当前请求的模型列表
        $currentModels = self::$modelList[$this->requestId] ?? [];

        foreach ($currentModels as $model) {
            $key = $model['model'].':'.$model['id'];
            if (empty($data[$key])) {
                $data[$key] = 1;
            } else {
                $data[$key] += 1;
            }
        }

        $list = [];
        foreach ($data as $model => $num) {
            $list[] = $model.' 「'.$num.'次」';
        }

        // 清理当前请求的数据，避免内存泄漏
        unset(self::$modelList[$this->requestId]);

        return $list;
    }

    private function getBaseInfo($sqlTimes = 0): array
    {
        // 获取基本信息 - 使用普通运算符提高性能
        $runtime = round(microtime(true) - $this->startTime, 3);
        $reqs = $runtime > 0 ? number_format(1 / $runtime, 2) : '∞';
        $base = [
            '请求信息' => $this->request->method().' '.$this->request->fullUrl(),
            '运行时间' => $runtime.'秒',
            '吞吐率' => $reqs.' req/s',
            '内存消耗' => size_format(memory_get_usage() - $this->startMemory),
            '查询时间' => $sqlTimes.'秒',
        ];

        try {
            if ($this->request->session()) {
                $base['会话信息'] = 'SESSION_ID='.$this->request->session()->getId();
            }
        } catch (Exception $e) {
            $base['会话信息'] = 'SESSION_ID=';
        }

        $base['PHP Version'] = phpversion();
        $base['Laravel Version'] = $this->app->version();
        $base['Environment'] = $this->app->environment();
        $base['Locale'] = $this->app->getLocale();

        // DB 数据库连接信息
        try {
            $dbConfig = DB::connection()->getConfig();
            $username = $dbConfig['username'] ?? '-';

            $base['DB Driver'] = ($dbConfig['driver'] ?? '-').'('.$this->maskIP($dbConfig['host'] ?? '-').') '.($dbConfig['charset'] ?? '-');
            $base['DB Connect'] = ($dbConfig['database'] ?? '-').'('.substr($username, 0, 2).'***'.substr($username, -2).')';
        } catch (Exception $e) {
            $base['DB Driver'] = '-';
            $base['DB Connect'] = '-';
        }

        // 操作系统名称
        $osName = php_uname('s');
        $friendlyOsName = match (strtoupper($osName)) {
            'DARWIN' => 'macOS',
            'LINUX' => 'Linux',
            'WINDOWS NT' => 'Windows',
            default => $osName,
        };

        $base['OS'] = $friendlyOsName.' v'.php_uname('r').' '.php_uname('m');

        // 磁盘空间信息（仅非 Windows 系统）
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            try {
                $directoryPath = '/';
                $totalSpace = disk_total_space($directoryPath);
                $freeSpace = disk_free_space($directoryPath);

                if ($totalSpace && $freeSpace) {
                    $useSpace = $totalSpace - $freeSpace;
                    $usageRate = round(($useSpace / $totalSpace) * 100, 2).'%';
                    $base['Disk Space'] = 'total:'.size_format($totalSpace).'; used:'.size_format($useSpace).'; free:'.size_format($freeSpace).'; usage:'.$usageRate;
                }
            } catch (Exception $e) {
                // 忽略磁盘空间获取错误
            }
        }

        return $base;
    }

    /**
     * IP地址掩码处理，隐藏中间部分
     *
     * @param  string  $ip
     * @return string
     */
    private function maskIP(string $ip): string
    {
        // 检查是否是空或特殊的地址
        if (empty($ip) || strlen($ip) < 5 || $ip === 'localhost' || $ip === '127.0.0.1') {
            return $ip;
        }

        // 验证是否为有效的IPv4地址
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // 尝试 IPv6 脱敏
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return $this->maskIPv6($ip);
            }
            return $ip;
        }

        // 将 IP 地址分割成数组
        $parts = explode('.', $ip);

        // 检查是否是标准的IPv4地址（4个部分）
        if (count($parts) !== 4) {
            return $ip;
        }

        // 验证每个部分是否为有效的数字
        foreach ($parts as $part) {
            if (! is_numeric($part) || (int) $part < 0 || (int) $part > 255) {
                return $ip;
            }
        }

        // 只保留第一个和最后一个部分，中间用 ***.*** 替换
        return $parts[0].'.***.***.'.$parts[3];
    }

    /**
     * IPv6 地址脱敏
     *
     * @param string $ip
     * @return string
     */
    private function maskIPv6(string $ip): string
    {
        // 简化的 IPv6 脱敏：只显示前2组和后2组
        $parts = explode(':', $ip);
        if (count($parts) < 4) {
            return '****:****:****:****';
        }

        $first = array_slice($parts, 0, 2);
        $last = array_slice($parts, -2, 2);

        return implode(':', $first) . ':****:****:' . implode(':', $last);
    }

    /**
     * 获取路由信息
     *
     * @param  bool  $hasParseError  是否包含语法错误信息
     * @return array|string[]
     *
     * @throws ReflectionException
     */
    private function getRouteInfo(bool $hasParseError): array
    {
        $route = $this->router->current();
        if (! $route instanceof \Illuminate\Routing\Route) {
            return [];
        }

        $uri = head($route->methods()).' '.$route->uri();
        $action = $route->getAction();
        $result = [
            'uri' => $uri ?: '-',
        ];
        $result = array_merge($result, $action);
        $controller = is_string($action['controller'] ?? null) ? $action['controller'] : '';
        $uses = $action['uses'] ?? null;

        if (! $hasParseError) {
            // 语法错误无法执行这个代码段
            if (str_contains($controller, '@')) {
                [$controller, $method] = explode('@', $controller);
                $reflector = $this->getCachedReflectionMethod($controller, $method);
                if ($reflector) {
                    $displayText = $this->getFilePath($reflector->getFileName()).'#'.$reflector->getStartLine().'-'.$reflector->getEndLine();
                    $result['file'] = $this->generateEditorLink($reflector->getFileName(), $reflector->getStartLine(), $displayText);
                }
                unset($result['uses']);
            } elseif ($uses instanceof Closure) {
                $reflector = new ReflectionFunction($uses);
                $displayText = $this->getFilePath($reflector->getFileName()).'#'.$reflector->getStartLine().'-'.$reflector->getEndLine();
                $result['file'] = $this->generateEditorLink($reflector->getFileName(), $reflector->getStartLine(), $displayText);
                $result['uses'] = $uses;
            } elseif (is_string($uses) && str_contains($uses, '@__invoke')) {
                if (class_exists($controller) && method_exists($controller, 'render')) {
                    $reflector = $this->getCachedReflectionMethod($controller, 'render');
                    if ($reflector) {
                        $displayText = $this->getFilePath($reflector->getFileName()).'#'.$reflector->getStartLine().'-'.$reflector->getEndLine();
                        $result['file'] = $this->generateEditorLink($reflector->getFileName(), $reflector->getStartLine(), $displayText);
                        $result['controller'] = $controller.'@render';
                    }
                }
            }
        } else {
            // 截取$controller 字符串里 @ 符号前面的字符串
            $result['controller'] = substr($controller, 0, strrpos($controller, '@'));
            unset($result['uses']);
        }

        $parametersObj = $route->parameters();
        $parameters = [];
        foreach ($parametersObj as $param) {
            if (is_object($param)) {
                if (method_exists($param, 'getRouteKey')) {
                    $parameters[] = get_class($param).':['.$param->getRouteKeyName().':'.$param->getRouteKey().']';
                } else {
                    $parameters[] = collect($param)->toArray();
                }
            } else {
                $parameters[] = $param;
            }
        }
        if ($parameters) {
            $result['params'] = $parameters;
        }

        $result['middleware'] = implode(', ', $route->middleware());
        $result['action'] = $route->getActionMethod();

        return $result;
    }

    /**
     * 获取缓存的反射方法
     *
     * @param  string  $class 类名
     * @param  string  $method 方法名
     * @return \ReflectionMethod|null
     */
    private function getCachedReflectionMethod(string $class, string $method): ?\ReflectionMethod
    {
        $key = $class.'::'.$method;

        if (! isset(self::$reflectionCache[$key])) {
            try {
                self::$reflectionCache[$key] = class_exists($class) && method_exists($class, $method)
                    ? new ReflectionMethod($class, $method)
                    : null;

                // 清理反射缓存，防止内存泄漏
                $this->cleanupReflectionCache();
            } catch (\Throwable) {
                self::$reflectionCache[$key] = null;
            }
        }

        return self::$reflectionCache[$key];
    }

    /**
     * 清理反射缓存，防止内存溢出
     *
     * @return void
     */
    private function cleanupReflectionCache(): void
    {
        if (count(self::$reflectionCache) > self::$maxReflectionCacheSize) {
            // 保留最近的一半缓存
            $halfSize = (int) (self::$maxReflectionCacheSize / 2);
            self::$reflectionCache = array_slice(self::$reflectionCache, -$halfSize, null, true);
        }
    }

    private function getSqlInfo(): array
    {
        $sqlTimes = 0;
        $sqlList = [];

        // 获取缓存的SQL分组配置
        if (self::$sqlGroupPatterns === null) {
            $groupConfig = self::getCachedConfig('trace.sql_groups', ['enabled' => true, 'groups' => []]);

            // 定义默认SQL分组规则
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
                    ]
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
                    ]
                ]
            ];

            // 合并配置和默认规则
            self::$sqlGroupPatterns = array_merge($defaultGroupPatterns, $groupConfig['groups'] ?? []);
            self::$sqlGroupingEnabled = $groupConfig['enabled'] ?? true;
        }

        $groupPatterns = self::$sqlGroupPatterns;
        $groupEnabled = self::$sqlGroupingEnabled;
        $collapsedByDefault = self::$configCache['trace.sql_groups']['collapsed_by_default'] ?? false;

        // 分类SQL语句
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
                'right' => !empty($item['time']) ? $item['time'].'ms' : '-',
            ];

            // 如果启用分组，判断SQL是否属于某个分组
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
                // 未启用分组，直接添加
                $sqlList[] = $sqlItem;
            }
        }

        // 构建最终的SQL列表，如果有分组则展示分组，否则直接展示
        if ($groupEnabled) {
            // 检查是否有分组数据
            $hasGroups = false;
            foreach (array_keys($groupPatterns) as $groupKey) {
                if (!empty($groupedSql[$groupKey])) {
                    $hasGroups = true;
                    break;
                }
            }

            if ($hasGroups) {
                // 添加其他SQL（业务SQL）
                if (!empty($groupedSql['other'])) {
                    $sqlList = $groupedSql['other'];
                }

                // 添加各个分组
                foreach ($groupPatterns as $groupKey => $group) {
                    if (!empty($groupedSql[$groupKey])) {
                        $sqlList[] = [
                            'type' => 'sql_group',
                            'group' => $groupKey,
                            'name' => $group['name'] ?? $groupKey,
                            'class' => $group['class'] ?? 'sql-group',
                            'collapsed' => $collapsedByDefault,
                            'sqls' => $groupedSql[$groupKey],
                            'count' => count($groupedSql[$groupKey])
                        ];
                    }
                }
            } else {
                // 没有分组，直接展示
                $sqlList = $groupedSql['other'];
            }
        }

        // 毫秒转秒
        $sqlTimes = $sqlTimes > 0 ? round($sqlTimes / 1000, 3) : 0;

        return [$sqlList, $sqlTimes];
    }

    /**
     * 获取会话信息
     *
     * @return array
     */
    private function getSessionInfo(): array
    {
        try {
            $session = app('session');
            if (empty($session)) {
                return $_SESSION ?? [];
            }

            return $session->all();
        } catch (Exception $e) {
            // 未装载 session
            return [];
        }
    }

    private function getRequestInfo(): array
    {
        return [
            'path' => $this->request->path(),
            'status_code' => $this->response->getStatusCode(),
            'format' => $this->request->getRequestFormat(),
            'content_type' => $this->response->headers->get('Content-Type') ? $this->response->headers->get('Content-Type') : 'text/html',
            'host' => $this->request->host(),
            'ip' => $this->request->ip(),
            // 'body'             => $this->request->all(),
            'request_query' => $this->request->query->all(),
            'request_request' => $this->request->request->all(),
            'request_headers' => $this->request->headers->all(),
            // 'request_cookies' => $this->request->cookies->all(),
            'response_headers' => $this->response->headers->all(),
        ];
    }

    public function getViewInfo(): array
    {
        $viewFiles = [];
        // 获取当前路由的其他视图文件
        foreach (app('view')->getFinder()->getViews() as $alias => $view) {
            $viewFiles[] = $alias.' ('.trim(str_replace(base_path(), '', $view), '/').')';
        }

        return $viewFiles;
    }

    /**
     * 添加调试信息
     */
    public function addMessage(mixed $var1, string $type = 'trace'): void
    {
        if (! is_enable_trace()) {
            return;
        }

        $stacktrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $stackItem = $stacktrace[0] ?? [];

        // 查找第一个不在 vendor 目录中的调用栈
        foreach ($stacktrace as $trace) {
            if (! isset($trace['file']) || str_contains($trace['file'], '/vendor/')) {
                continue;
            }

            $stackItem = $trace;
            break;
        }

        if (empty($stackItem) || !isset($stackItem['file'])) {
            return;
        }

        $baseFilePath = $this->getFilePath($stackItem['file']);
        $this->messages[] = [
            'var' => $var1,
            'local' => basename($baseFilePath).'#'.$stackItem['line'],
            'type' => 'trace',
            'right' => strtoupper($type),
            'file_path' => $stackItem['file'],
            'base_path' => $baseFilePath,
            'line' => $stackItem['line'] ?? 1,
        ];
    }

    public function getFilePath($file = ''): string
    {
        if (empty($file)) {
            return '';
        }

        $basePath = base_path();

        // 如果文件路径不包含基础路径，直接返回原路径
        if (!str_contains($file, $basePath)) {
            return $file;
        }

        return substr($file, strlen($basePath) + 1);
    }
}
