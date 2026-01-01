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

    protected array $messages = [];

    /** @var Request */
    protected $request;

    /** @var Response */
    protected $response;

    // 实例化并传入参数

    /**
     * @param  Application  $app
     *
     * @throws BindingResolutionException
     */
    public function __construct(mixed $app = null, array $config = [])
    {
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
     */
    public function listenModelEvent(): void
    {
        $events = ['retrieved', 'creating', 'created', 'updating', 'updated', 'saving', 'saved', 'deleting', 'deleted', 'restoring', 'restored', 'replicating'];
        foreach ($events as $event) {
            Event::listen('eloquent.'.$event.':*', function ($listenString, $model) use ($event) {
                $this->logModelEvent($listenString, $model, $event);
            });
        }
    }

    /**
     * 监听 SQL事件
     *
     * @return void
     */
    protected function listenSql(): void
    {
        // DB::enableQueryLog();
        $events = isset($this->app['events']) ? $this->app['events'] : null;
        try {
            // 监听SQL执行
            $events->listen(function (QueryExecuted $query) {
                $this->addQuery($query);
            });
        } catch (Exception $e) {
        }

        try {
            // 监听事务开始
            $events->listen(\Illuminate\Database\Events\TransactionBeginning::class, function ($transaction) {
                $this->addTransactionQuery('Begin Transaction', $transaction->connection);
            });
            // 监听事务提交
            $events->listen(\Illuminate\Database\Events\TransactionCommitted::class, function ($transaction) {
                $this->addTransactionQuery('Commit Transaction', $transaction->connection);
            });

            // 监听事务回滚
            $events->listen(\Illuminate\Database\Events\TransactionRolledBack::class, function ($transaction) {
                $this->addTransactionQuery('Rollback Transaction', $transaction->connection);
            });

            $connectionEvents = [
                'beganTransaction' => 'Begin Transaction', // 开始事务
                'committed' => 'Commit Transaction', // 提交事务
                'rollingBack' => 'Rollback Transaction', // 回滚事务
            ];
            foreach ($connectionEvents as $event => $eventName) {
                $events->listen('connection.*.'.$event, function ($event, $params) use ($eventName) {
                    $this->addTransactionQuery($eventName, $params[0]);
                });
            }
            // 监听连接创建
            $events->listen(function (\Illuminate\Database\Events\ConnectionEstablished $event) {
                $this->addTransactionQuery('Connection Established', $event->connection);
            });
        } catch (Exception $e) {
        }
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

    protected function logModelEvent($listenString, $model, $event): void
    {
        $model = isset($model[0]) ? $model[0] : $model;
        // 使用: 分割 $model , 获取模型名称
        $modelName = trim(explode(':', $listenString)[1]);

        $modelId = $model->getKey();

        self::$modelList[] = [
            'model' => $modelName,
            'id' => $modelId,
            'event' => $event,
        ];
    }

    public function output($response): string
    {
        if (! is_enable_trace()) {
            // 运行在命令行下
            return '';
        }
        $this->response = $response;

        $exception = [];
        $hasParseError = false; // 判断是否有语法错误
        // 判断响应数据 $response 中是否有异常数据 exception
        if (property_exists($response, 'exception') && ! empty($response->exception)) {
            $exceptionObj = $response->exception;
            $hasParseError = $exceptionObj instanceof ParseError; // 判断是否有语法错误
            $exceptionString = $this->getExceptionContent($response->exception);
            $fileName = $this->getFilePath($exceptionObj->getFile()); //
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
        if ($this->request->isMethod('get') && ! request()->expectsJson() && ! ($response instanceof \Illuminate\Http\JsonResponse) && ! app()->environment('production')) {
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

    private function getModelList(): array
    {
        $data = [];
        foreach (self::$modelList as $model) {
            if (empty($data[$model['model'].':'.$model['id']])) {
                $data[$model['model'].':'.$model['id']] = 1;
            } else {
                $data[$model['model'].':'.$model['id']] += 1;
            }
        }
        $list = [];
        foreach ($data as $model => $num) {
            $list[] = $model.' 「'.$num.'次」';
        }

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
            '吞吐率' => $reqs.'req/s',
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

        $base['PHP version'] = phpversion();
        $base['Laravel version'] = $this->app->version();
        $base['environment'] = $this->app->environment();
        $base['locale'] = $this->app->getLocale();

        // DB 数据库连接信息
        $dbConfig = DB::connection()->getConfig();
        $username = $dbConfig['username'] ?? '-';

        $base['DB Driver'] = ($dbConfig['driver'] ?? '-').'('.$this->maskIP($dbConfig['host'] ?? '-').') '.($dbConfig['charset'] ?? '-');
        $base['DB Connect'] = ($dbConfig['database'] ?? '-').'('.substr($username, 0, 2).'***'.substr($username, -2).')';

        // 操作系统名称
        $osName = php_uname('s');
        // 根据需要，你可以将系统名称转换为更友好的格式
        $friendlyOsName = match (strtoupper($osName)) {
            'DARWIN' => 'macOS',
            'LINUX' => 'Linux',
            'WINDOWS NT' => 'Windows',
            default => $osName,
        };
        // 系统信息
        $base['OS'] = $friendlyOsName.' v'.php_uname('r').' '.php_uname('m');
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $directoryPath = '/'; // 根目录
            $totalSpace = disk_total_space($directoryPath); // 磁盘总空间
            $freeSpace = disk_free_space($directoryPath); // 磁盘可用空间
            $useSpace = bcsub($totalSpace, $freeSpace, 0); // 磁盘已用空间
            $usageRate = bcmul(bcdiv($useSpace, $totalSpace, 5), 100, 2).'%'; // 磁盘使用率
            $base['Disk Space'] = 'total:'.size_format($totalSpace).'; used:'.size_format($useSpace).'; free:'.size_format($freeSpace).'; usage-rate:'.$usageRate;
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
        $stackItem = $stacktrace[0];
        foreach ($stacktrace as $trace) {
            if (! isset($trace['file']) || str_contains($trace['file'], '/vendor/')) {
                continue;
            }

            $stackItem = $trace;
            break;
        }
        if (empty($stackItem)) {
            return;
        }
        $baseFilePath = $this->getFilePath($stackItem['file']);
        $this->messages[] = [
            'var' => $var1, // 传入的变量调试值
            'local' => basename($baseFilePath).'#'.$stackItem['line'], // 文件名+行号',
            'type' => 'trace',
            'right' => strtoupper($type),
            'file_path' => $stackItem['file'],
            'base_path' => $baseFilePath, // 相对于 项目 的路径
            'line' => $stackItem['line'] ?? 1,
        ];

    }

    public function getFilePath($file = ''): string
    {
        return substr($file, strlen(base_path()) + 1);
    }
}
