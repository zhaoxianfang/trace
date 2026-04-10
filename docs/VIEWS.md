# Trace 视图使用文档

本文档详细介绍 Trace 扩展包提供的 Blade 视图模板及其使用方法。

## 概述

Trace 扩展包提供以下视图模板：

| 视图名称 | 文件路径 | 用途 | 访问权限 |
|---------|---------|------|---------|
| `trace::error` | `resources/views/error.blade.php` | 统一错误页面 | 公开 |
| `trace::debug` | `resources/views/debug.blade.php` | 调试信息展示 | 公开 |
| `trace::panel` | `resources/views/panel.blade.php` | 调试面板（底部工具栏） | **内部专用** |

## trace::error - 错误页面

用于展示 HTTP 错误信息，支持生产环境和调试模式的不同展示。

### 使用示例

```php
// 基本用法
return response()->view('trace::error', [
    'code' => 404,
    'title' => '页面未找到',
    'message' => '您访问的页面不存在或已被移除',
]);

// 完整参数
return response()->view('trace::error', [
    'code' => 500,
    'title' => '服务器错误',
    'message' => '处理请求时发生错误',
    'isDebug' => true,
    'exception' => $exception,
    'list' => [
        ['type' => 'text', 'label' => '错误信息', 'value' => $exception->getMessage()],
        ['type' => 'debug_file', 'label' => '文件', 'value' => $exception->getFile(), 'file' => $exception->getFile(), 'line' => $exception->getLine()],
        ['type' => 'code_html', 'label' => '代码', 'value' => $exceptionContent],
    ],
    'requestId' => 'abc123',
    'timestamp' => now()->format('Y-m-d H:i:s'),
], 500);
```

### 参数说明

| 参数 | 类型 | 必填 | 默认值 | 说明 |
|-----|------|------|-------|------|
| `code` | int | 否 | 500 | HTTP 状态码 |
| `title` | string | 否 | 'Error' | 错误标题 |
| `message` | string | 否 | 'An error occurred' | 错误消息 |
| `isDebug` | bool | 否 | false | 是否调试模式 |
| `exception` | \Throwable | 否 | null | 异常对象（调试模式显示详情） |
| `list` | array | 否 | [] | 调试信息列表（调试模式显示） |
| `requestId` | string | 否 | 自动生成 | 请求 ID |
| `timestamp` | string | 否 | 当前时间 | 时间戳 |

### 样式特性

- **动态背景** - 渐变背景 + 浮动气泡动画
- **粒子效果** - 随机粒子漂浮动画
- **状态码主题** - 根据状态码自动切换颜色（4xx/5xx/3xx）
- **毛玻璃卡片** - 半透明背景 + 模糊效果
- **响应式设计** - 完美适配移动端
- **暗色模式** - 自动适配系统主题

## trace::debug - 调试页面

用于展示调试信息列表，支持多种数据类型展示。

### 使用示例

```php
// 基本用法
return response()->view('trace::debug', [
    'title' => '用户信息调试',
    'list' => [
        ['type' => 'text', 'label' => '用户名', 'value' => 'admin'],
        ['type' => 'text', 'label' => '用户ID', 'value' => 12345],
        ['type' => 'code', 'label' => '配置信息', 'value' => json_encode($config, JSON_PRETTY_PRINT)],
    ],
]);

// 使用 debug_file 类型
return response()->view('trace::debug', [
    'title' => '异常调试',
    'list' => [
        ['type' => 'text', 'label' => '错误信息', 'value' => $e->getMessage()],
        ['type' => 'debug_file', 'label' => '文件位置', 'value' => $e->getFile() . ':' . $e->getLine(), 'file' => $e->getFile(), 'line' => $e->getLine()],
        ['type' => 'code_html', 'label' => '代码片段', 'value' => $codeSnippet],
    ],
]);
```

### 参数说明

| 参数 | 类型 | 必填 | 默认值 | 说明 |
|-----|------|------|-------|------|
| `title` | string | 否 | 'Debug' | 页面标题 |
| `list` | array | 否 | [] | 调试信息列表 |

### List 项类型

每个 list 项是一个数组，包含以下字段：

| 字段 | 类型 | 必填 | 说明 |
|-----|------|------|------|
| `type` | string | 是 | 数据类型：text, code, code_html, debug_file |
| `label` | string | 是 | 标签名称 |
| `value` | mixed | 是 | 值内容 |
| `file` | string | 否 | 文件路径（debug_file 类型使用） |
| `line` | int | 否 | 行号（debug_file 类型使用） |
| `editor` | string | 否 | 编辑器类型（debug_file 类型使用） |

### 类型说明

- **text** - 纯文本显示
- **code** - 代码块显示（自动转义 HTML）
- **code_html** - HTML 代码块（不转义，用于语法高亮）
- **debug_file** - 文件链接，点击可在编辑器中打开

### 样式特性

- **深色主题** - 紫色渐变背景
- **动态网格** - 移动网格背景动画
- **浮动光晕** - 动态光晕效果
- **毛玻璃卡片** - 半透明调试卡片
- **代码高亮** - 深色代码块 + 错误行红色高亮
- **行内布局** - 标签和值在同一行显示

## trace::panel - 调试面板

> **⚠️ 内部专用视图**
> 
> 此视图仅限 Trace 扩展包内部使用，外部代码直接调用会抛出异常。
> 
> 如需自定义调试面板外观，请通过 CSS 覆盖样式。

### 内部使用方式

```php
// 在 TraceResponseTrait 中内部调用
$view = view('trace::panel', [
    'tabs' => ['messages' => 'Messages', 'sql' => 'SQL'],
    'badges' => ['messages' => 3, 'sql' => 15],
    'contents' => [...],
    'performance' => ['time' => '120ms', 'memory' => '12MB'],
]);
```

### CSS 隔离

调试面板使用严格的 CSS 隔离，防止外部样式干扰：

```css
#trace-debug-panel,
#trace-debug-panel *,
#trace-debug-panel *::before,
#trace-debug-panel *::after {
    all: initial;
    box-sizing: border-box !important;
}
```

### 自定义样式

如需覆盖调试面板样式，可以在页面中添加自定义 CSS：

```html
<style>
/* 修改面板背景色 */
#trace-debug-panel .trace-toolbar {
    background: linear-gradient(135deg, #your-color-1 0%, #your-color-2 100%) !important;
}

/* 修改标签页颜色 */
#trace-debug-panel .trace-tab.active {
    color: #your-active-color;
    border-bottom-color: #your-active-color;
}
</style>
```

## 自定义视图

### 发布视图文件

```bash
php artisan vendor:publish --provider="zxf\Trace\Providers\TraceServiceProvider" --tag="trace-views"
```

发布后，视图文件将复制到 `resources/views/vendor/trace/` 目录。

### 自定义错误视图

在 `config/trace.php` 中配置：

```php
'fallback_handler' => [
    'custom_error_view' => 'errors.my-custom-error',
],
```

创建 `resources/views/errors/my-custom-error.blade.php`：

```blade
@extends('layouts.app')

@section('content')
<div class="error-container">
    <h1>{{ $code }}</h1>
    <p>{{ $message }}</p>
    @if($isDebug && isset($exception))
        <pre>{{ $exception->getTraceAsString() }}</pre>
    @endif
</div>
@endsection
```

## 视图保护机制

Trace 扩展包实现了视图访问保护机制：

1. **公开视图** - `trace::error`, `trace::debug` 可供外部调用
2. **内部视图** - `trace::panel` 仅限 Trace 包内部使用
3. **调用栈检查** - 通过检查调用栈验证调用来源

### 视图保护实现

```php
// ViewGuard 类自动注册
ViewGuard::register();

// 检查是否可以访问
if (!ViewGuard::canAccess('trace::panel')) {
    throw new \RuntimeException("Access denied: View 'trace::panel' is for internal use only.");
}
```

## 最佳实践

1. **错误处理** - 优先使用 `trace::error` 展示错误信息
2. **调试信息** - 使用 `trace::debug` 展示开发调试数据
3. **不要直接调用 panel** - 使用 Trace 包提供的 API 自动渲染调试面板
4. **自定义样式** - 通过 CSS 覆盖而非修改视图文件
5. **生产环境** - 确保 `isDebug` 参数为 false，避免泄露敏感信息

## 示例代码

### 完整的异常处理示例

```php
// 在 App\Exceptions\Handler 中
public function render($request, Throwable $exception)
{
    $code = $this->getStatusCode($exception);
    
    if ($request->expectsJson()) {
        return response()->json([
            'code' => $code,
            'message' => $exception->getMessage(),
        ], $code);
    }
    
    $debugList = [];
    if (config('app.debug')) {
        $debugList = [
            ['type' => 'text', 'label' => '异常类', 'value' => get_class($exception)],
            ['type' => 'debug_file', 'label' => '文件', 'value' => $exception->getFile() . ':' . $exception->getLine(), 
             'file' => $exception->getFile(), 'line' => $exception->getLine()],
            ['type' => 'code', 'label' => '堆栈', 'value' => $exception->getTraceAsString()],
        ];
    }
    
    return response()->view('trace::error', [
        'code' => $code,
        'title' => class_basename(get_class($exception)),
        'message' => $exception->getMessage(),
        'isDebug' => config('app.debug'),
        'exception' => $exception,
        'list' => $debugList,
    ], $code);
}
```

### 调试信息输出示例

```php
// 在控制器中
public function debug(Request $request)
{
    $data = [
        'request_method' => $request->method(),
        'request_url' => $request->url(),
        'user_id' => auth()->id(),
        'config' => config('app.name'),
    ];
    
    return app('trace')->outputDebugHtml([
        ['type' => 'text', 'label' => '请求方法', 'value' => $data['request_method']],
        ['type' => 'text', 'label' => '请求URL', 'value' => $data['request_url']],
        ['type' => 'text', 'label' => '用户ID', 'value' => $data['user_id']],
        ['type' => 'code', 'label' => '配置', 'value' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)],
    ], '请求调试信息');
}
```
