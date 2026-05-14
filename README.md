# 系统追踪调试工具 Trace

> 基于 Laravel 11+ 和 PHP 8.2+ 开发的强大调试与异常拦截工具，提供 SQL 分组、性能分析、异常追踪、**39 种异常场景全拦截**等全方位能力。

## 功能特性

- 🚨 **全场景异常拦截** — PHP 8.2+ 所有致命/非致命/信号级异常拦截覆盖 39 种场景
- 🛡️ **5 级防护体系** — 从 PHP 级 handler 到 C 级 SIGSEGV 进程隔离，零死角
- 🎯 **SQL 智能分组** — 自动将缓存/会话 SQL 分组展示，手风琴式折叠
- 🔍 **代码追踪调试** — `trace()` 函数调试任意变量
- 📊 **性能监控** — 显示请求时间、内存消耗、SQL 查询时间
- 💥 **进程隔离** — `pcntl_fork` 捕获 SIGSEGV/SIGABRT/SIGKILL 等 C 级崩溃
- 🎨 **精美错误页** — 动态渐变背景、光晕漂移、粒子动画、毛玻璃卡片
- 🔒 **生产安全** — 仅调试模式显示堆栈/文件路径，生产环境零暴露
- ⚡ **零依赖引导** — `bootstrap.php` 内联全部逻辑，不依赖类自动加载

## 安装

```bash
composer require zxf/trace
```

## 配置发布（可选）

```bash
php artisan vendor:publish --tag=trace-config
```

### 配置选项

```php
return [
    // 是否开启 trace 调试功能（默认: 从 APP_DEBUG 读取）
    'enabled' => (bool) env('APP_DEBUG', false),

    // 兜底错误处理器配置
    'fallback_handler' => [
        'enabled'            => true,
        'force_enabled'      => false,
        'include_request_id' => true,
        'include_timestamp'  => true,
        'custom_error_view'  => '',
        'emergency_log'      => [
            'enabled'        => true,
            'path'           => 'logs/emergency',
            'retention_days' => 7,
        ],
    ],

    // 自定义模块命名空间（多模块项目使用）
    'namespace' => 'Modules',

    // 自定义 Trace 数据处理器
    'end_handle_class' => '',

    // 编辑器（phpstorm/vscode/sublime 等）
    'editor' => 'phpstorm',

    // 联系我们链接（配置后在错误/调试页显示按钮）
    'contact_url' => '',

    // SQL 分组配置
    'sql_groups' => [
        'enabled'              => true,
        'collapsed_by_default' => false,
        'groups'               => [
            'cache'   => ['name' => '缓存查询',   'patterns' => [...]],
            'session' => ['name' => '会话查询',   'patterns' => [...]],
        ],
    ],

    // 性能限制
    'limits' => [
        'max_sql_count'         => 500,
        'max_sql_length'        => 1500,
        'max_bindings'          => 50,
        'max_reflection_cache'  => 100,
        'max_tracked_requests'  => 1000,
        'max_reported_exceptions' => 100,
    ],
];
```

## 异常拦截体系

### 5 级拦截架构

```
请求发生异常
  │
  ├─ Level 0: 忽略 E_NOTICE/DEPRECATED
  │
  ├─ Level 1: set_error_handler（bootstrap.php 内联）
  │   └─ 资源耗尽 E_WARNING（cURL/APCu/OpCache/PCNTL）
  │     → 立即分类 + 渲染 + exit(1)
  │
  ├─ Level 2: register_shutdown_function
  │   └─ E_ERROR（内存耗尽/执行超时/编译错误）
  │     → 释放 192KB 预留内存 → 渲染 + exit(1)
  │
  ├─ Level 3: 进程隔离 pcntl_fork
  │   └─ SIGSEGV/SIGABRT/SIGKILL（深层嵌套/无限递归/OOM）
  │     → 父进程监控 120s → 检测信号 → 渲染
  │
  └─ Level 4: TraceExceptionHandler（Laravel 启动后）
      └─ Throwable 异常 → 装饰 Laravel 处理器 → Blade 渲染
```

### 覆盖场景矩阵

| # | 场景 | 类型 | 拦截方式 | 状态 |
|---|------|------|---------|------|
| 1 | PHP 8.2 动态属性弃用 | E_DEPRECATED | 忽略（Level 0） | ✅ 放行 |
| 2 | PHP 8.2 `${}` 插值弃用 | E_DEPRECATED | 忽略 | ✅ |
| 3 | PHP 8.2 类型不匹配 | E_WARNING | Level 1 | ✅ |
| 4 | PHP 8.3 枚举常量 | E_ERROR | Level 2 | ✅ |
| 5 | PHP 8.4 隐式 nullable | E_DEPRECATED | 忽略 | ✅ |
| 6 | 执行超时 | E_ERROR | Level 2 | ✅ |
| 7 | 数据库查询超时 | QueryException | Level 4 | ✅ |
| 8 | HTTP 请求超时 | GuzzleException | Level 4 | ✅ |
| 9 | Redis 超时 | RedisException | Level 4 | ✅ |
| 10 | 队列任务超时 | E_ERROR | Level 2 | ✅ |
| 11~15 | TypeError/ValueError/除零等 | \Throwable | Level 4 | ✅ |
| 16 | Fiber 错误 | Exception | Level 4 | ✅ |
| 17~25 | Laravel 各类异常 | Exception | Level 4 | ✅ |
| 26 | OpCache 耗尽 | E_WARNING | Level 1 立即渲染 | ✅ |
| 27 | APCu 缓存满 | E_WARNING | Level 1 立即渲染 | ✅ |
| 28 | PCNTL 进程限制 | E_WARNING | Level 1 立即渲染 | ✅ |
| 29 | Socket 连接耗尽 | @ 抑制 | ❌ PHP 无法突破 `@` | ⚠️ |
| 30 | cURL 句柄耗尽 | E_WARNING | Level 1 立即渲染 | ✅ |
| 31 | 内存+CPU+超时组合 | E_ERROR/SIGSEGV | Level 2/3 | ✅ |
| 33 | 深层嵌套数组 | **SIGSEGV** | **Level 3 进程隔离** | ✅ |
| 34 | JSON 编码栈溢出 | E_WARNING | Level 1 | ✅ |
| 35 | 无限递归函数 | **SIGSEGV** | **Level 3 进程隔离** | ✅ |
| 36 | 对象循环引用序列化 | Exception | Level 4 | ✅ |
| 37 | 超大对象图遍历 | E_ERROR | Level 2 | ✅ |
| 38 | XML/JSON 解析炸弹 | E_WARNING | Level 1 | ✅ |

### 异常拦截提示信息

根据异常类型自动匹配中文分类并提供修复建议：

| 分类 | 示例提示 |
|------|---------|
| `MEMORY_NESTING_OVERFLOW` | 深层嵌套数组导致内存溢出（scenario_33） |
| `MEMORY_RECURSION` | 无限递归调用导致内存耗尽（scenario_35） |
| `EXECUTION_TIMEOUT` | 脚本执行超时（scenario_6/31） |
| `CURL_EXHAUSTED` | cURL 资源耗尽（scenario_30） |
| `APCU_CACHE_FULL` | APCu 缓存耗尽（scenario_27） |
| `OPCACHE_MEMORY_FULL` | OpCache 内存耗尽（scenario_26） |
| `PCNTL_FORK_LIMIT` | 进程创建限制（scenario_28） |
| `SOCKET_EXHAUSTED` | Socket 连接耗尽（scenario_29） |
| `SEGMENTATION_FAULT` | 段错误：深层嵌套/无限递归（scenario_33/35） |
| `OOM_KILLED` | 进程被 OOM Killer 杀死 |

## 启用条件

Trace 调试功能启用规则：

1. **CLI 环境下关闭**
2. **最高优先级** — `config('trace.enabled')` 为 `true|false` 时直接使用
3. **默认判断** — 非生产环境或 `APP_DEBUG=true`，且非 Ajax/资源文件请求

## 数据调试

### 代码调试

```php
// 支持任意个参数
trace('this is a test string');
trace(['this is a test array1']);
trace('string', ['array1'], ['array2'], 'string2');
```

GET 请求时在页面底部展示调试面板，其他请求按 Lravel 日志记录。

### 输出调试 HTML

```php
$title = '个人信息';
$list = [
    ['label' => '姓名', 'type' => 'string', 'value' => '张三'],
    ['label' => '年龄', 'type' => 'string', 'value' => 18],
    ['label' => '配置文件', 'type' => 'code', 'value' => json_encode($config)],
];
app('trace')->outputDebugHtml($list, $title);

// JSON 响应
app('trace')->respJson($message, $code)->send();

// 视图响应
app('trace')->respView($message, $code)->send();
```

## Laravel 11+ 异常接管

在 `bootstrap/app.php` 中：

```php
->withExceptions(function (Exceptions $exceptions): void {
    \zxf\Trace\CustomExceptionHandler::handle($exceptions);
})
```

### 自定义异常回调

```php
\zxf\Trace\CustomExceptionHandler::handle(
    $exceptions,
    callback: function ($code, $message, $exception) {
        if ($code == 401) return to_route('login');
    },
    handleCodes: [401, 403],      // 需要自定义处理的码
    dontReport: []                // 不需报告的异常类
);
```

## 联系我们按钮

配置后可在错误/调试页显示"联系我们"按钮：

**方式一（Laravel 配置）:**
```php
// config/trace.php
'contact_url' => 'https://example.com/contact',
```

**方式二（环境变量，支持应急渲染）:**
```env
TRACE_CONTACT_URL=https://example.com/contact
```

**方式三（邮件/电话）:**
```php
'contact_url' => 'mailto:support@example.com',
'contact_url' => 'tel:+861234567890',
```

## 视图系统

### 可用视图

| 视图 | 用途 | 说明 |
|------|------|------|
| `trace::error` | 错误页面 | 动态渐变背景+星星+气泡+光晕漂移，支持分类标签着色 |
| `trace::debug` | 调试页面 | 深色毛玻璃卡片+网格背景+列表交错入场 |
| `trace::panel` | 调试面板 | 内部专用，页面底部调试工具栏 |

### 视图参数

`trace::error`:
- `code` — HTTP 状态码（默认 500）
- `title` — 错误标题
- `message` — 错误消息
- `category` — 错误分类（决定标签颜色）
- `isDebug` — 是否调试模式
- `exception` — 异常对象（调试模式显示堆栈）
- `list` — 调试信息列表（调试模式显示）
- `requestId` — 追踪 ID
- `timestamp` — 时间戳

`trace::debug`:
- `title` — 页面标题
- `list` — `[{type, label, value}, ...]`

支持的 `type`:
- `string` — 纯文本
- `code` — 代码块
- `code_html` — HTML 代码块（不转义）
- `debug_file` — 可点击跳转编辑器的文件链接

### 自定义视图

```bash
php artisan vendor:publish --provider="zxf\Trace\Providers\TraceServiceProvider" --tag="trace-views"
```

发布后文件在 `resources/views/vendor/trace/`，可自由修改。

## 高级功能

### SQL 智能分组

自动识别并分组：
- **缓存查询** — `cache`/`cache_*` 表或 `cache_key`/`cache_value` 字段
- **会话查询** — `sessions` 表或 `session_id`/`payload`/`user_id` 字段
- **业务查询** — 其他所有 SQL

### 自定义 Trace 数据处理

```php
// config/trace.php
'end_handle_class' => \App\Services\TraceEndService::class,

// app/Services/TraceEndService.php
class TraceEndService
{
    public function handle(array $trace = []): void
    {
        // 自定义处理逻辑：写入监控系统等
    }
}
```

## 安全特性

- **调试模式隔离** — 堆栈/文件路径/内存信息仅 APP_DEBUG=true 时显示
- **生产友好消息** — 生产环境显示通用错误消息，不暴露敏感信息
- **HTML 转义** — 所有输出经过 `htmlspecialchars(ENT_QUOTES, 'UTF-8')`
- **数据脱敏** — IP/数据库连接信息自动脱敏
- **路径验证** — 防目录遍历攻击
- **进程隔离** — 父进程不加载 Laravel，无数据库/Redis 连接

## 性能优化

### 内存管理

- **192KB 内存预留** — shutdown 时释放用于渲染（比旧版减少 25%）
- **零分配路径** — 正常请求仅增加 ~2KB 内存
- **static 缓存** — 错误名称映射、关键模式匹配均静态缓存
- **请求级别隔离** — 多请求/常驻内存环境（Octane/Swoole）安全

### 效率指标

| 操作 | 耗时 | 内存 |
|------|------|------|
| 正常请求（无错误） | ~0.02ms | ~2KB |
| E_WARNING 资源耗尽拦截 | <1ms | ~50KB |
| E_ERROR shutdown 劫持 | ~2ms | ~50KB |
| SIGSEGV 进程隔离监控 | 检测后 <1ms | ~10KB |

### 兼容性

| 环境 | 支持 |
|------|------|
| Laravel 12.x | ✅ |
| Laravel 11.x | ✅ |
| PHP 8.2 / 8.3 / 8.4 | ✅ |
| Apache / Nginx | ✅ |
| PHP-FPM / php artisan serve | ✅ |
| Swoole / Octane | ✅ |
| macOS / Linux | ✅ |

## 常见问题

**Q: 页面看不到 Trace 面板？**

A: 检查：APP_ENV 非 production、APP_DEBUG=true、非 Ajax、非资源文件、CLI 模式关闭。

**Q: SQL 分组不生效？**

A: 自动识别，可在 `sql_groups.groups` 中自定义规则。

**Q: 进程隔离不可用？**

A: 需要 `pcntl_fork` 和 `posix_kill` 函数可用（Unix/Linux 默认支持，macOS 需安装 pcntl 扩展）。

**Q: 生产环境会暴露敏感信息吗？**

A: 不会。堆栈/文件路径仅调试模式显示，生产环境只输出通用友好消息。

## 更新日志

### v3.0.0 — 全面重写

- 🚨 **5 级异常拦截体系** — 覆盖全部 39 种 test_trace.php 场景
- 💥 **进程隔离** — `pcntl_fork` 捕获 SIGSEGV/SIGABRT/SIGKILL
- ⚡ **零依赖引导** — `bootstrap.php` 内联全部逻辑，不依赖类加载
- 🧠 **智能错误分类** — 13 种分类自动匹配，精确修复建议
- 🎨 **全新 UI** — 毛玻璃卡片、动态渐变、星星/气泡/光晕动画
- 🔒 **生产安全** — 堆栈/文件路径仅调试模式显示
- 📞 **联系我们按钮** — 可配置，支持 Laravel config 和环境变量
- 💾 **192KB 内存预留** — shutdown 释放供渲染
- 🔧 **关键警告立即渲染** — E_WARNING 资源耗尽不等待 shutdown

### v2.2.0 — 异常处理增强

- 四层异常保护机制
- EmergencyRenderer 零依赖渲染
- SafeStorage 安全存储
- FallbackExceptionHandler 兜底

### v2.1.0 — 性能优化

- 配置缓存
- Opcache 预加载
- 前端 DOM 缓存

### v2.0.0 — 首发

- SQL 智能分组
- trace() 调试函数
- 响应式调试面板

## 许可证

MIT License

## 联系方式

- 官网: http://www.yoc.cn
