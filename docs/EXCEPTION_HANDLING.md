# 异常处理文档

本文档详细介绍 `zxf/trace` 扩展包的异常处理机制，包括在各种极端情况下的错误处理方案。

> 📖 **相关文档**: [视图文档](VIEWS.md) - 详细的视图使用指南和参数说明

## 目录

- [概述](#概述)
- [异常处理层次](#异常处理层次)
- [Laravel 11+ 配置](#laravel-11-配置)
- [Laravel 10 及以下配置](#laravel-10-及以下配置)
- [自定义错误视图](#自定义错误视图)
- [配置选项](#配置选项)
- [使用场景示例](#使用场景示例)
- [架构说明](#架构说明)

## 概述

本扩展包提供了多层次的异常处理机制，确保在各种情况下（包括 Laravel 框架无法正常工作时）都能向用户展示友好的错误页面。

## 异常处理层次

```
┌─────────────────────────────────────────────────────────────┐
│                    异常处理层次结构                          │
├─────────────────────────────────────────────────────────────┤
│  Layer 1: Laravel 11+ withExceptions()                      │
│           → CustomExceptionHandler::handle()                │
│           适用于: 正常请求处理阶段的异常                       │
├─────────────────────────────────────────────────────────────┤
│  Layer 2: TraceExceptionHandler (Laravel 10及以下)          │
│           → 通过 TraceServiceProvider 注册                   │
│           适用于: 传统异常处理流程                            │
├─────────────────────────────────────────────────────────────┤
│  Layer 3: FallbackExceptionHandler                          │
│           → 通过 register_shutdown_function 注册             │
│           适用于: 引导阶段异常、服务提供者异常、致命错误        │
│           特点: 与 Laravel 11+ 兼容，不覆盖原生处理器          │
├─────────────────────────────────────────────────────────────┤
│  Layer 4: EmergencyRenderer                                 │
│           → 完全独立的渲染器，零依赖                         │
│           适用于: Laravel 完全无法工作时的兜底方案            │
│           特点: 支持 HTML/JSON/纯文本多格式输出               │
└─────────────────────────────────────────────────────────────┘
```

### 协作式处理器设计

**FallbackExceptionHandler** 采用协作式设计：
- **不覆盖** Laravel 的 `set_exception_handler` 和 `set_error_handler`
- 仅使用 `register_shutdown_function` 捕获 PHP 致命错误
- 当 Laravel 正常启动后，自动让位给 Laravel 的异常处理器
- 仅在以下情况介入：
  - Laravel 未启动完成时的致命错误
  - Laravel 处理器未能处理的错误
  - 引导阶段异常

这种设计确保了与 Laravel 11+ 的完美兼容性，避免了处理器冲突。

## Laravel 11+ 配置

在 `bootstrap/app.php` 中配置异常处理：

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 配置 Trace 包异常处理
        \zxf\Trace\CustomExceptionHandler::handle(
            $exceptions,
            // 自定义处理回调（可选）
            function ($code, $message, $exception) {
                // 处理特定状态码
                if ($code == 401) {
                    return redirect()->route('login');
                }
                // 返回 null 让 Trace 继续处理
                return null;
            },
            // 需要执行回调的错误码（可选，空数组表示所有）
            [401, 403, 404],
            // 不需要报告的异常类（可选）
            [
                \Illuminate\Auth\AuthenticationException::class,
                \Illuminate\Validation\ValidationException::class,
            ]
        );
    })->create();
```

## 处理场景

### 1. 正常请求异常

这是最常见的场景，异常发生在请求处理过程中：

```php
// 控制器中的异常
public function index()
{
    // 这些异常会被正常捕获并显示友好页面
    throw new \Exception('Something went wrong');
}
```

**处理流程：**
1. Laravel 捕获异常
2. 调用 `CustomExceptionHandler::handleExceptionRender()`
3. 根据请求类型返回 JSON 或 HTML 响应

### 2. 文件缓存损坏

当缓存文件损坏时（如 `storage/framework/cache` 中的文件）：

```php
// 扩展包会自动检测并清理损坏的缓存文件
// 在 TraceServiceProvider::registerBootstrapProtection() 中实现
```

**处理流程：**
1. `TraceServiceProvider` 在注册时验证缓存配置
2. 发现损坏文件时自动清理
3. 如果清理失败，使用 `FallbackExceptionHandler` 捕获
4. 最终通过 `EmergencyRenderer` 显示错误页面

### 3. 配置加载异常

当 `config/trace.php` 或其他配置文件存在语法错误时：

**处理流程：**
1. Laravel 在引导阶段抛出异常
2. `FallbackExceptionHandler::handleException()` 捕获
3. `EmergencyRenderer::render()` 显示独立错误页面
4. 错误信息记录到 `storage/logs/emergency-*.log`

### 4. 服务提供者异常

当服务提供者的 `register()` 或 `boot()` 方法抛出异常时：

**处理流程：**
1. 异常在引导阶段被抛出
2. `FallbackExceptionHandler` 的异常处理器捕获
3. 由于 Laravel 可能未完全启动，使用 `EmergencyRenderer`
4. 显示友好错误页面，包含调试信息（如果开启 debug）

### 5. PHP 致命错误

当出现语法错误、内存耗尽等致命错误时：

```php
// 示例：语法错误
Parse error: syntax error, unexpected '}' in ...

// 示例：内存耗尽
Fatal error: Allowed memory size of ... exhausted
```

**处理流程：**
1. PHP 触发致命错误
2. `FallbackExceptionHandler::handleShutdown()` 捕获
3. `EmergencyRenderer::render()` 显示错误页面
4. 避免显示空白页面或 PHP 默认错误

### 6. 内存耗尽错误

当内存耗尽时的特殊处理：

```php
// EmergencyRenderer 使用精简的内存占用方式
// 避免在内存紧张时进一步消耗内存
```

**优化措施：**
- 使用静态方法避免对象实例化
- 精简错误模板，避免复杂的字符串操作
- 优先使用内存缓存而非文件操作

## 错误响应格式

### HTML 响应（Web 请求）

```html
<!DOCTYPE html>
<html>
<head>
    <title>服务器错误 - 500</title>
    <!-- 美观的错误页面样式 -->
</head>
<body>
    <div class="container">
        <span class="emoji">💥</span>
        <div class="code">500</div>
        <h1>服务器内部错误</h1>
        <p>服务器内部错误，请稍后重试</p>
        <a href="/" class="btn">返回首页</a>
        <!-- 调试模式下显示详细信息 -->
    </div>
</body>
</html>
```

### JSON 响应（API 请求）

```json
{
    "success": false,
    "code": 500,
    "message": "服务器内部错误，请稍后重试",
    "debug": {
        "type": "Exception",
        "original_message": "...",
        "file": "...",
        "line": 42,
        "trace": [...]
    }
}
```

### 纯文本响应（CLI）

```
Error 500: 服务器内部错误，请稍后重试

Type: Exception
Message: ...
File: /path/to/file.php:42

Stack Trace:
#0 ...
#1 ...
```

## 配置选项

在 `config/trace.php` 中配置异常处理：

```php
return [
    // 是否开启 trace 调试
    'enabled' => (bool) env('APP_DEBUG', false),

    // 兜底错误处理配置
    'fallback_handler' => [
        // 是否启用兜底错误处理器
        'enabled' => true,

        // 是否在调试模式下也启用
        'force_enabled' => false,

        // 是否在响应中包含请求ID
        'include_request_id' => true,

        // 是否在响应中包含时间戳
        'include_timestamp' => true,

        // 自定义错误页面路径
        'custom_error_view' => '',

        // 紧急日志配置
        'emergency_log' => [
            'enabled' => true,
            'path' => 'logs/emergency',
            'retention_days' => 7,
        ],
    ],

    // ... 其他配置
];
```

## 日志记录

### 正常异常日志

异常信息会记录到 Laravel 的日志系统中：

```php
// 在 storage/logs/laravel.log 中
[2024-01-15 10:30:45] production.ERROR: [异常]:服务器内部错误 ...
```

### 紧急日志

当 Laravel 日志系统不可用时，使用紧急日志：

```
// 在 storage/logs/emergency-2024-01-15.log 中
[2024-01-15 10:30:45] [EMERGENCY] 错误消息 | Context: Exception Handler | Trace: ...
```

## 视图命名空间

Trace 扩展包注册了自己的视图命名空间 `trace`，可以通过 `trace::` 前缀访问扩展包内的视图。

### 视图查找优先级

当发生错误需要渲染视图时，系统按以下优先级查找：

1. **用户自定义视图** (`config trace.fallback_handler.custom_error_view`)
2. **应用标准错误视图** (`resources/views/errors/{$code}.blade.php`)
3. **应用分组错误视图** (`resources/views/errors/{4xx|5xx}.blade.php`)
4. **Trace 包特定状态码视图** (`trace::errors.{$code}`)
5. **Trace 包通用错误视图** (`trace::errors.generic`)
6. **内置 HTML 模板**（最终兜底）

### 发布并自定义 Trace 错误视图

```bash
# 发布 Trace 的错误视图到 resources/views/vendor/trace/
php artisan vendor:publish --tag=trace-views
```

发布后，您可以修改 `resources/views/vendor/trace/errors/` 目录下的视图文件。

### Trace 包提供的错误视图

#### 统一错误视图（推荐）
- `trace::errors.error` - **统一的 HTTP 错误视图**（推荐，支持所有状态码）
  - 自动根据状态码显示对应的表情符号、标题和描述
  - 内置 400, 401, 403, 404, 405, 408, 422, 429, 500, 502, 503, 504 的默认配置
  - 支持自定义消息和建议列表
  - 自动显示调试信息（调试模式下）

#### 专用错误视图
- `trace::errors.fatal` - 致命错误视图（深色主题，带有 SVG 动画）
- `trace::errors.generic` - 通用错误页面（向后兼容）
- `trace::errors.minimal` - 极简错误页面（用于紧急场景）

#### 布局视图
- `trace::layouts.error` - 错误页面布局（可被自定义视图继承）
- `trace::emergency` - 紧急错误视图（独立完整 HTML，用于框架不可用时）

### 方法 1：使用 Laravel 错误视图

创建 `resources/views/errors/500.blade.php`：

```blade
@extends('layouts.error')

@section('content')
    <div class="error-page">
        <h1>{{ $code }}</h1>
        <p>{{ $message }}</p>
        <a href="/">返回首页</a>
    </div>
@endsection
```

### 方法 2：配置自定义视图路径

```php
// config/trace.php
'fallback_handler' => [
    'custom_error_view' => 'errors.custom',
],
```

### 方法 3：完全自定义渲染

在 `CustomExceptionHandler::handle()` 的回调中：

```php
\zxf\Trace\CustomExceptionHandler::handle(
    $exceptions,
    function ($code, $message, $exception) {
        if ($code == 500) {
            return response()->view('my-custom-error', [
                'code' => $code,
                'message' => $message,
                'exception' => $exception,
            ], $code);
        }
        return null;
    }
);
```

### 方法 4：在 Blade 视图中使用 Trace 布局

```blade
{{-- resources/views/errors/500.blade.php --}}
@extends('trace::layouts.error')

@section('title', '服务器错误')
@section('emoji', '💥')
@section('heading', '500 - 服务器内部错误')
@section('message', '抱歉，服务器遇到了一个错误。')

@section('content')
    @parent
    {{-- 添加自定义内容 --}}
    <div class="custom-message">
        <p>如有疑问，请联系技术支持</p>
    </div>
@endsection
```

## 调试模式

在 `.env` 中设置：

```env
APP_DEBUG=true
```

调试模式下：
- 显示详细的错误信息
- 包含堆栈跟踪
- 显示异常文件和行号
- 提供编辑器链接（如果配置了 editor）

生产环境请确保：

```env
APP_DEBUG=false
```

## 测试异常处理

### 模拟引导阶段异常

```php
// 在 config/app.php 顶部添加语法错误
<?php
// 故意制造语法错误
{
```

### 模拟服务提供者异常

```php
// 在某个 ServiceProvider::register() 中
public function register()
{
    throw new \Exception('Provider Error');
}
```

### 模拟致命错误

```php
// 在路由中
Route::get('/fatal', function () {
    // 内存耗尽
    $data = [];
    while (true) {
        $data[] = str_repeat('x', 1000000);
    }
});
```

## 性能考虑

1. **内存使用**：
   - `EmergencyRenderer` 使用精简模板
   - 限制堆栈跟踪深度
   - 使用静态方法减少对象创建

2. **响应时间**：
   - 优先使用内存缓存
   - 避免在错误处理中执行复杂操作
   - 延迟日志写入

3. **并发处理**：
   - 使用请求ID隔离不同请求
   - 防止递归和死循环
   - 限制最大错误处理次数

## 故障排除

### 仍然看到白屏

1. 检查 `storage/logs/` 权限
2. 确认 `config/trace.php` 配置正确
3. 检查 PHP 错误日志

### 错误页面样式丢失

`EmergencyRenderer` 使用内联样式，不依赖外部资源。

### 自定义视图不生效

1. 确认视图文件存在
2. 检查配置 `custom_error_view` 路径
3. 清除视图缓存

## 更多帮助

如有问题，请查看：
- 源码注释
- `storage/logs/` 中的日志
- 浏览器开发者工具中的网络响应
