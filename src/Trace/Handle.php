<?php

namespace zxf\Trace;

use Closure;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Router;
use ParseError;
use Throwable;
use zxf\Trace\Traits\AppEndTrait;
use zxf\Trace\Traits\ExceptionCustomCallbackTrait;
use zxf\Trace\Traits\ExceptionShowDebugHtmlTrait;
use zxf\Trace\Traits\ExceptionTrait;
use zxf\Trace\Traits\InfoCollectorTrait;
use zxf\Trace\Traits\ModelCollectorTrait;
use zxf\Trace\Traits\RouteCollectorTrait;
use zxf\Trace\Traits\SqlCollectorTrait;
use zxf\Trace\Traits\TraceResponseTrait;
use zxf\Trace\Traits\ViewCollectorTrait;

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
    use ExceptionCustomCallbackTrait;
    // 解决 generateRequestId 方法冲突：ExceptionShowDebugHtmlTrait 和 ExceptionNotifyTrait 都有此方法
    // ExceptionTrait 内部已经使用了 ExceptionCodeTrait 和 ExceptionNotifyTrait
    use ExceptionTrait {
        ExceptionTrait::generateRequestId insteadof ExceptionShowDebugHtmlTrait;
    }
    use ExceptionShowDebugHtmlTrait {
        ExceptionShowDebugHtmlTrait::generateRequestId as generateRequestIdFromHtmlTrait;
    }
    // 数据收集 Traits
    use SqlCollectorTrait;
    use ModelCollectorTrait;
    use ViewCollectorTrait;
    use RouteCollectorTrait;
    use InfoCollectorTrait;
    // 渲染 Trait
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

    // 视图数据收集：按请求ID存储每个视图的详细信息（路径 + 参数）
    protected static array $viewDataCollector = [];

    // 模型事件类型标记：区分 DB查询 / 关联引用 / 写操作
    protected static array $modelEventTypes = [];

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

        // 提前检查：如果 trace 未启用，跳过所有调试收集初始化
        // 仅在请求开始时做一次判断，避免重复调用 is_enable_trace()
        if (! is_enable_trace()) {
            return;
        }

        // 从配置读取限制值
        $this->loadConfigLimits();

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
        $this->listenViewComposing();
    }

    /**
     * 加载配置中的限制值
     */
    protected function loadConfigLimits(): void
    {
        $limits = config('trace.limits', []);
        $this->maxSqlListSize = $limits['max_sql_count'] ?? 500;
        $this->maxSqlLength = $limits['max_sql_length'] ?? 1500;
        $this->maxBindings = $limits['max_bindings'] ?? 50;
    }

    /**
     * 获取当前请求ID
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * 获取请求开始时间
     */
    protected function getStartTime(): float
    {
        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            return $_SERVER['REQUEST_TIME_FLOAT'];
        }

        if (isset($this->app['request'])) {
            $time = $this->app['request']->server('REQUEST_TIME_FLOAT');
            if ($time !== null) {
                return (float) $time;
            }
        }

        if (defined('LARAVEL_START')) {
            return LARAVEL_START;
        }

        return microtime(true);
    }

    /**
     * 输出 Trace 调试信息
     *
     * @param  Response  $response  HTTP 响应对象
     * @return string Trace 调试 HTML 内容
     */
    public function output($response): string
    {
        if (! is_enable_trace()) {
            return '';
        }

        if (self::$currentRequestId !== $this->requestId) {
            return '';
        }

        $this->response = $response;

        $exception = [];
        $hasParseError = false;

        // 获取异常对象
        $exceptionObj = null;

        if ($this->currentException instanceof Throwable) {
            $exceptionObj = $this->currentException;
        } elseif (property_exists($response, 'exception') && ! empty($response->exception)) {
            $exceptionObj = $response->exception;
        } elseif (property_exists($this, 'initErr') && self::$initErr && ! empty(self::$message ?? null)) {
            $fileName = (self::$content['file:'] ?? '');
            $line = (int)(self::$content['line:'] ?? 0);
            $code = (int)(self::$content['code:'] ?? 500);
            $message = self::$message;
            $exceptionContent = $this->getExceptionContent(self::$errObj ?? new \Exception($message));

            $editor = config('trace.editor') ?? 'phpstorm';
            $exception = [
                'message' => $message,
                'line' => $line,
                'exception' => [
                    'raw_html' => true,
                    'content' => $exceptionContent,
                ],
                'file' => [
                    'raw_html' => true,
                    'content' => '<span class="json-string-content" style="font-size:13px;"><a href="' . $editor . '://open?file=' . urlencode($fileName) . '&amp;line=' . $line . '" class="phpdebugbar-link">' . $this->getFilePath($fileName) . '#' . $line . '</a></span>',
                ],
                'code' => $code,
            ];
        }

        if ($exceptionObj instanceof Throwable) {
            $hasParseError = $exceptionObj instanceof ParseError;
            $exceptionString = $this->getExceptionContent($exceptionObj);
            $fileName = $this->getFilePath($exceptionObj->getFile());
            $editor = config('trace.editor') ?? 'phpstorm';
            $exception = [
                'message' => $exceptionObj->getMessage(),
                'line' => $exceptionObj->getLine(),
                'exception' => [
                    'raw_html' => true,
                    'content' => $exceptionString,
                ],
                'file' => [
                    'raw_html' => true,
                    'content' => '<span class="json-string-content" style="font-size:13px;"><a href="' . $editor . '://open?file=' . urlencode($exceptionObj->getFile()) . '&amp;line=' . $exceptionObj->getLine() . '" class="phpdebugbar-link">' . ($fileName . '#' . $exceptionObj->getLine()) . '</a></span>',
                ],
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
        // 使用白名单映射替代变量变量（$$name），防止潜在的安全风险
        $trace = [];
        $dataMap = [
            'messages' => $messages,
            'sql' => $sql,
            'base' => $base,
            'route' => $route,
            'view' => $view,
            'models' => $models,
            'exception' => $exception,
            'session' => $session,
            'request' => $request,
        ];
        foreach ($this->config['tabs'] as $name => $title) {
            $name = strtolower($name);
            $sourceData = $dataMap[$name] ?? [];
            $result = [];
            if (is_array($sourceData)) {
                foreach ($sourceData as $subTitle => $item) {
                    $result[$subTitle] = $item;
                }
            }
            $showTips = in_array($name, ['messages', 'sql', 'models']) && ! empty($result) ? ' (' . count($result) . ')' : '';
            $showTips = in_array($name, ['exception']) && ! empty($result) ? ' 🔴' : $showTips;

            $trace[$title . $showTips] = ! empty($result) ? $result : $this->getEmptyTips($name);
        }

        try {
            $this->traceEndHandle($trace);
        } catch (Exception $e) {
            if (config('app.debug', false)) {
                error_log('[Trace] traceEndHandle failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
            return '';
        }

        if ($this->request->isMethod('get') && ! $this->request->expectsJson() && ! ($response instanceof \Illuminate\Http\JsonResponse) && ! $this->app->environment('production')) {
            return $this->randerPage($trace);
        }

        return '';
    }

    /**
     * 获取空状态下的tab提示信息
     */
    private function getEmptyTips(?string $tabName = ''): array
    {
        [$message, $tips] = match (strtolower($tabName)) {
            'messages' => ['暂无调试内容', '可使用 trace(mixed ...$args) 函数进行调试'],
            'sql' => ['暂无 SQL 查询', '执行数据库操作后将在此显示'],
            'view' => ['没有加载视图', '加载 Blade 视图后将在此显示'],
            'exception' => ['暂无异常信息', ''],
            'models' => ['暂无模型操作记录', 'Eloquent 模型操作将在此显示'],
            'route' => ['暂无路由信息', ''],
            'base' => ['暂无基础信息', ''],
            'session' => ['暂无会话信息', ''],
            default => ['暂无内容', ''],
        };

        return [
            [
                'is_empty_tips' => true,
                'message' => $message,
                'tips' => $tips,
            ],
        ];
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
            'local' => basename($baseFilePath) . '#' . $stackItem['line'],
            'type' => 'trace',
            'right' => strtoupper($type),
            'file_path' => $stackItem['file'],
            'base_path' => $baseFilePath,
            'line' => $stackItem['line'] ?? 1,
        ];
    }

    /**
     * 获取相对项目根目录的文件路径
     */
    public function getFilePath($file = ''): string
    {
        if (empty($file)) {
            return '';
        }

        $basePath = base_path() ?: '';

        if (!str_contains($file, $basePath)) {
            return $file;
        }

        return substr($file, strlen($basePath) + 1);
    }
}
