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

    /**
     * 实例化并初始化请求级别状态
     *
     * @param  Application  $app
     *
     * @throws BindingResolutionException
     */
    public function __construct(mixed $app = null, array $config = [])
    {
        // 生成唯一的请求 ID
        $this->requestId = uniqid('trace_', true);

        // 更新全局当前请求 ID
        self::$currentRequestId = $this->requestId;
        self::$requestCounter++;

        if (is_enable_trace()) {
            $this->startMemory = memory_get_usage();

            if (! $app) {
                $app = app();   // Fallback when $app is not given
            }
            $this->app = $app;
            $this->router = $this->app['router'];
            $this->startTime = $this->app['request']->server('REQUEST_TIME_FLOAT') ?? constant('LARAVEL_START');

            $this->request = $app['request'];
            $this->config = array_merge($this->config, $config);

            $this->listenModelEvent();
            $this->listenSql();
        }
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

        $events = isset($this->app['events']) ? $this->app['events'] : null;
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
        }

        try {
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

            $connectionEvents = [
                'beganTransaction' => 'Begin Transaction', // 开始事务
                'committed' => 'Commit Transaction', // 提交事务
                'rollingBack' => 'Rollback Transaction', // 回滚事务
            ];
            foreach ($connectionEvents as $event => $eventName) {
                $events->listen('connection.*.'.$event, function ($event, $params) use ($eventName) {
                    if (self::$currentRequestId === $this->requestId) {
                        $this->addTransactionQuery($eventName, $params[0]);
                    }
                });
            }
            // 监听连接创建
            $events->listen(function (\Illuminate\Database\Events\ConnectionEstablished $event) {
                if (self::$currentRequestId === $this->requestId) {
                    $this->addTransactionQuery('Connection Established', $event->connection);
                }
            });
        } catch (Exception $e) {
        }

        // 标记事件监听器已注册
        self::$eventListenersRegistered = true;
    }

    /**
     * 记录sql
     *
     * @param  QueryExecuted  $event
     */
    private function addQuery($event): void
    {
        // 获取绑定的参数
        $bindings = $event->bindings;
        $sql = $event->sql;

        // 替换参数占位符
        foreach ($bindings as $binding) {
            $binding = is_string($binding) ? "'{$binding}'" : $binding;
            $sql = preg_replace('/\?/', $binding, $sql, 1);
        }
        $this->sqlList[] = [
            'sql' => $sql,
            'type' => 'Query',
            'time' => $event->time, // 'ms'
            // 'connection' => $event->connectionName, // eg: mysql
        ];
    }

    /**
     * 记录事务sql
     *
     * @param  string  $event
     * @param  \Illuminate\Database\Connection  $connection
     * @return void
     */
    private function addTransactionQuery($event, $connection): void
    {
        $this->sqlList[] = [
            'sql' => '['.$connection->getName().':'.$connection->getConfig('driver').'] '.$event,
            'type' => 'Transaction',
            'time' => 0,
            // 'connection' => $connection->getName(),
            // 'driver'     => $connection->getConfig('driver'),
        ];
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

        $model = isset($model[0]) ? $model[0] : $model;

        // 使用: 分割 $model , 获取模型名称
        $modelName = trim(explode(':', $listenString)[1]);

        $modelId = $model->getKey();

        // 使用请求 ID 作为键，避免不同请求的数据混淆
        if (! isset(self::$modelList[$this->requestId])) {
            self::$modelList[$this->requestId] = [];
        }

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

    // 获取空状态下的tab 提示信息
    private function getEmptyTips(?string $tabName=''):array
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
        // 获取基本信息
        $runtime = bcsub(microtime(true), $this->startTime, 3);
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
                    $useSpace = bcsub($totalSpace, $freeSpace, 0);
                    $usageRate = bcmul(bcdiv($useSpace, $totalSpace, 5), 100, 2).'%';
                    $base['Disk Space'] = 'total:'.size_format($totalSpace).'; used:'.size_format($useSpace).'; free:'.size_format($freeSpace).'; usage:'.$usageRate;
                }
            } catch (Exception $e) {
                // 忽略磁盘空间获取错误
            }
        }

        return $base;
    }

    private function maskIP($ip)
    {
        // 检查是否是空或特殊的地址
        if (empty($ip) || strlen($ip) < 5 || $ip === 'localhost' || $ip === '127.0.0.1') {
            return $ip;
        }

        // 验证是否为有效的IPv4地址
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $ip; // 如果不是有效的IPv4，直接返回原值
        }

        // 将 IP 地址分割成数组
        $parts = explode('.', $ip);

        // 检查是否是标准的IPv4地址（4个部分）
        if (count($parts) !== 4) {
            return $ip; // 如果不是4个部分，直接返回原值
        }

        // 只保留第一个和最后一个部分，中间用 ***.*** 替换
        return $parts[0].'.***.***.'.$parts[3];
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
        if (! is_a($route, 'Illuminate\Routing\Route')) {
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
                if (class_exists($controller) && method_exists($controller, $method)) {
                    $reflector = new ReflectionMethod($controller, $method);
                }
                unset($result['uses']);
            } elseif ($uses instanceof Closure) {
                $reflector = new ReflectionFunction($uses);
                $result['uses'] = $uses;
            } elseif (is_string($uses) && str_contains($uses, '@__invoke')) {
                if (class_exists($controller) && method_exists($controller, 'render')) {
                    $reflector = new ReflectionMethod($controller, 'render');
                    $result['controller'] = $controller.'@render';
                }
            }
        } else {
            // 截取$controller 字符串里 @ 符号前面的字符串
            $result['controller'] = substr($controller, 0, strrpos($controller, '@'));
            unset($result['uses']);
        }

        // 运行某个控制器方法的那几行
        if (isset($reflector)) {
            $fileName = $this->getFilePath($reflector->getFileName()); //

            $editor = config('trace.editor') ?? 'phpstorm';
            // $result['file'] = $fileName . ':' . $reflector->getStartLine() . '-' . $reflector->getEndLine();
            $result['file'] = '<span class="json-label"><a href="'.$editor.'://open?file='.urlencode($reflector->getFileName()).'&amp;line='.$reflector->getStartLine().'" class="phpdebugbar-link">'.($fileName.'#'.$reflector->getStartLine().'-'.$reflector->getEndLine()).'</a></span>';
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

    private function getSqlInfo(): array
    {
        // $this->sqlList = DB::getQueryLog(); // 获取查询sql

        $sqlTimes = 0;
        // $this->sqlList 里面包含 sql、time、type 字段
        foreach ($this->sqlList as &$item) {
            if (! isset($item['time'])) {
                continue;
            }
            $sqlTimes = bcadd($sqlTimes, $item['time'], 3);
            $item = [
                'label' => $item['sql'],
                'right' => !empty($item['time'])?$item['time'].'ms':'-',
            ];
        }
        // 毫秒转秒
        $sqlTimes = $sqlTimes > 0 ? bcdiv($sqlTimes, 1000, 3) : 0;

        return [$this->sqlList, $sqlTimes];
    }

    private function getSessionInfo()
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
