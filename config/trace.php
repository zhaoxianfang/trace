<?php

/*
 |--------------------------------------------------------------------------
 | Trace 代码调试和错误处理
 |--------------------------------------------------------------------------
 |
 | 下面这个 trace 配置是可选的；可以自定义配置trace的每一项，也不可以不配置任意一项，甚至
 | 不需要你单独定trace配置项，zxf/trace 中会使用默认配置处理。
 |
 */

return [
    /**
     * 是否开启 trace 调试功能
     * 默认: true
     */
    'enabled' => true,

    /**
     * 使用自定义处理的命名空间，例如在 App\Exceptions\Handler->render 中自定义处理 Trace 检测到的异常
     * 有些项目使用 Modules 多模块，这个配置会变得很有用
     *
     * 默认: Modules
     */
    'namespace' => 'Modules',

    /**
     * 自定义处理 Trace 调试产生的数据
     * 默认:空
     *    例如:
     *    'end_handle_class' => \App\Services\TraceEndService::class,
     *    // 表示在 TraceEndHandle 类中接管 Trace 调试产生的数据
     *
     *    use Illuminate\Support\Facades\Log;
     *
     *    class TraceEndService
     *    {
     *        public function handle(array $trace=[]): void
     *        {
     *            // 做点什么...
     *            // Log::channel('stack')->debug("===== [Trace]调试: ===== ", $trace);
     *        }
     *    }
     */
    'end_handle_class' => '',

    /*
    |--------------------------------------------------------------------------
    | 代码追踪调试使用的编辑器
    |--------------------------------------------------------------------------
    |
    | 设置代码调试编辑器，调试工具会引导点击链接跳转到编辑器的指定位置，
    | 默认: phpstorm
    |
    | 支持: "phpstorm", "vscode", "vscode-insiders", "vscode-remote",
    |            "vscode-insiders-remote", "vscodium", "textmate", "emacs",
    |            "sublime", "atom", "nova", "macvim", "idea", "netbeans",
    |            "xdebug", "espresso"
    |
    */
    'editor' => 'phpstorm',

    /*
    |--------------------------------------------------------------------------
    | 调试选项
    |--------------------------------------------------------------------------
    */
    'options' => [
        /**
         * 是否在生产环境中启用 Trace（不建议）
         * 默认: false
         */
        'enable_in_production' => false,

        /**
         * 最大 SQL 查询日志数量（防止内存溢出）
         * 默认: 100
         */
        'max_sql_queries' => 100,

        /**
         * 最大 SQL 查询长度（字符数）
         * 默认: 5000
         */
        'max_sql_length' => 5000,

        /**
         * 是否记录模型事件
         * 默认: true
         */
        'log_model_events' => true,

        /**
         * 是否记录数据库事务
         * 默认: true
         */
        'log_transactions' => true,

        /**
         * 是否在生产环境中隐藏敏感信息（如数据库凭据）
         * 默认: true
         */
        'hide_sensitive_data' => true,

        /**
         * 调试信息显示模式
         * 选项: 'auto' | 'always' | 'never'
         * - auto: 根据环境自动判断（默认）
         * - always: 始终显示
         * - never: 从不显示
         * 默认: 'auto'
         */
        'display_mode' => 'auto',

        /**
         * 最大异常堆栈跟踪深度
         * 默认: 50
         */
        'max_trace_depth' => 50,

        /**
         * 是否启用请求性能监控
         * 默认: true
         */
        'enable_performance_monitor' => true,

        /**
         * 慢查询阈值（毫秒）
         * 超过此阈值的查询会被标记为慢查询
         * 默认: 1000 (1秒)
         */
        'slow_query_threshold' => 1000,

        /**
         * 是否在调试面板中显示内存使用情况
         * 默认: true
         */
        'show_memory_usage' => true,

        /**
         * 是否在调试面板中显示执行时间
         * 默认: true
         */
        'show_execution_time' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | 安全配置
    |--------------------------------------------------------------------------
    */
    'security' => [
        /**
         * 是否启用 HTML 内容转义（防止 XSS 攻击）
         * 默认: true
         */
        'escape_html_output' => true,

        /**
         * 允许的编辑器列表（防止恶意配置）
         * 默认: 常见的编辑器
         */
        'allowed_editors' => [
            'phpstorm', 'vscode', 'vscode-insiders', 'vscode-remote',
            'vscode-insiders-remote', 'vscodium', 'textmate', 'emacs',
            'sublime', 'atom', 'nova', 'macvim', 'idea', 'netbeans',
            'xdebug', 'espresso'
        ],

        /**
         * 是否验证资源文件路径（防止目录遍历）
         * 默认: true
         */
        'validate_asset_paths' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | 性能优化
    |--------------------------------------------------------------------------
    */
    'performance' => [
        /**
         * 是否启用资源文件缓存
         * 默认: true
         */
        'enable_asset_cache' => true,

        /**
         * 是否启用路由缓存
         * 默认: true
         */
        'enable_route_cache' => true,

        /**
         * 是否启用反射属性缓存
         * 默认: true
         */
        'enable_reflection_cache' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | 日志配置
    |--------------------------------------------------------------------------
    */
    'logging' => [
        /**
         * 是否记录 SQL 查询日志
         * 默认: true
         */
        'log_sql_queries' => true,

        /**
         * 是否记录异常日志
         * 默认: true
         */
        'log_exceptions' => true,

        /**
         * 日志通道（Laravel 日志配置中的通道名）
         * 默认: stack
         */
        'log_channel' => 'stack',

        /**
         * 是否在日志中包含堆栈跟踪
         * 默认: true
         */
        'include_stack_trace' => true,

        /**
         * 最大日志条目数（防止日志文件过大）
         * 默认: 1000
         */
        'max_log_entries' => 1000,
    ],
];
