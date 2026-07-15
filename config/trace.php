<?php

/**
 * Trace 代码调试和错误处理配置文件
 *
 * 注意：此配置文件是可选的。如果不创建此文件，zxf/trace 将使用默认配置。
 * 可以只配置需要的选项，未配置的选项将使用默认值。
 */

return [
    /**
     * 是否开启 trace 调试功能
     *
     * 默认: 从环境变量 APP_DEBUG 读取
     * 可选项: true | false
     */
    'enabled' => (bool) env('APP_DEBUG', false),

    /**
     * 兜底错误处理配置
     *
     * 用于配置在 Laravel 框架无法正常工作时（如引导阶段异常、致命错误）的错误处理行为
     */
    'fallback_handler' => [
        // 是否启用兜底错误处理器
        'enabled' => true,

        // 是否在调试模式下也启用兜底处理器（通常只在生产环境启用）
        'force_enabled' => false,

        // 是否在响应中包含请求ID（便于追踪问题）
        'include_request_id' => true,

        // 是否在响应中包含时间戳
        'include_timestamp' => true,

        // 自定义错误页面路径（留空使用默认模板）
        'custom_error_view' => '',

        // 紧急日志配置
        'emergency_log' => [
            // 是否启用紧急日志
            'enabled' => true,

            // 日志存储路径（相对于 storage_path）
            'path' => 'logs/emergency',

            // 日志文件保留天数
            'retention_days' => 7,
        ],
    ],

    /**
     * 使用自定义处理的命名空间
     *
     * 例如在多模块项目中（如 Modules），可以设置此值来自定义模块命名空间。
     * 这样在 App\Exceptions\Handler->render 中可以自定义处理 Trace 检测到的异常。
     *
     * 默认: 'Modules'
     */
    'namespace' => 'Modules',

    /**
     * 自定义处理 Trace 调试产生的数据
     *
     * 可以指定一个类来处理 Trace 收集的数据，例如发送到监控系统或日志服务。
     *
     * 示例:
     * 'end_handle_class' => \App\Services\TraceEndService::class,
     *
     * class TraceEndService
     * {
     *     public function handle(array $trace = []): void
     *     {
     *         // 自定义处理逻辑
     *         Log::channel('stack')->debug('[Trace] 调试数据: ', $trace);
     *     }
     * }
     *
     * 默认: '' (不启用)
     */
    'end_handle_class' => '',

    /**
     * 代码追踪调试使用的编辑器
     *
     * 设置后，调试面板中的文件链接将使用指定的编辑器协议打开。
     *
     * 支持的编辑器:
     * - "phpstorm" (默认)
     * - "vscode", "vscode-insiders", "vscode-remote", "vscode-insiders-remote", "vscodium"
     * - "textmate", "emacs", "sublime", "atom", "nova", "macvim"
     * - "idea", "netbeans", "xdebug", "espresso"
     */
    'editor' => 'phpstorm',

    /**
     * 联系我们链接地址
     *
     * 如果配置此参数，在错误页面和调试页面的操作按钮区域会显示"联系我们"按钮。
     * 用户点击后跳转到配置的 URL 地址。
     *
     * 示例:
     * - 'https://example.com/contact'
     * - 'mailto:support@example.com'
     * - 'tel:+861234567890'
     *
     * 默认: '' (空字符串表示不显示)
     */
    'contact_url' => '',

    /**
     * SQL 分组配置
     *
     * 自动将 SQL 查询按类型分组，便于识别缓存查询、会话查询等。
     */
    'sql_groups' => [
        // 是否启用 SQL 分组功能
        'enabled' => true,

        // 分组默认是否折叠
        'collapsed_by_default' => false,

        // 分组规则定义
        'groups' => [
            'cache' => [
                'name' => '缓存查询',
                'class' => 'sql-group-cache',
                'patterns' => [
                    '/select\s+.*\s+from\s+`?cache`?/i',
                    '/select\s+.*\s+from\s+`?cache_[\w]+`?/i',
                    '/insert\s+into\s+`?cache`?/i',
                    '/update\s+`?cache`?\s+set/i',
                    '/delete\s+from\s+`?cache`?/i',
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
                ],
            ],
        ],
    ],

    /**
     * 性能限制配置
     *
     * 用于防止内存溢出和性能问题。
     */
    'limits' => [
        // SQL 查询最大记录数
        'max_sql_count' => 500,

        // 单条 SQL 最大长度（字符数）
        'max_sql_length' => 1500,

        // 最大绑定参数数量
        'max_bindings' => 50,

        // 反射缓存最大条目数
        'max_reflection_cache' => 100,

        // 请求跟踪最大条目数（中间件使用）
        'max_tracked_requests' => 1000,

        // 已报告异常最大条目数
        'max_reported_exceptions' => 100,

        // 单次请求最多记录的模型事件数量（防止内存无限增长导致 OOM）
        'max_model_count' => 1000,
    ],

    /**
     * 性能监控配置
     *
     * 用于监控 Trace 包本身的性能指标。
     */
    'performance_monitoring' => [
        // 是否启用性能监控
        'enabled' => false,

        // 是否记录到日志
        'log_to_file' => false,

        // 性能阈值（毫秒），超过此值会记录警告
        'threshold_ms' => 100,
    ],
];
