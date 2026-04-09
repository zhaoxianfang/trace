# Trace 视图文档

本文档详细介绍 Trace 包提供的所有 Blade 视图及其使用方法。

## 目录

- [错误视图](#错误视图)
- [调试视图](#调试视图)
- [组件视图](#组件视图)
- [布局视图](#布局视图)
- [使用示例](#使用示例)

---

## 错误视图

### 1. errors.error（推荐）

**统一的 HTTP 错误视图**，支持所有标准 HTTP 状态码。

#### 特点

- 自动根据状态码显示对应的表情符号、标题和描述
- 智能显示操作按钮（刷新/返回根据错误类型自动选择）
- 支持自定义消息和建议列表
- 调试模式下自动显示详细的异常信息
- 响应式设计，支持移动端

#### 参数

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `code` | int | 是 | HTTP 状态码 |
| `message` | string | 否 | 自定义错误消息 |
| `emoji` | string | 否 | 自定义表情符号 |
| `title` | string | 否 | 自定义标题 |
| `suggestions` | array | 否 | 建议/原因列表 |
| `isDebug` | bool | 否 | 是否显示调试信息 |
| `exception` | Throwable | 否 | 异常对象（用于调试） |
| `requestId` | string | 否 | 请求ID |
| `timestamp` | string | 否 | 时间戳 |

#### 使用示例

```blade
{{-- 基本使用 --}}
@include('trace::errors.error', ['code' => 404])

{{-- 带自定义消息 --}}
@include('trace::errors.error', [
    'code' => 403,
    'message' => '您没有权限访问此资源'
])

{{-- 完整示例 --}}
@include('trace::errors.error', [
    'code' => 500,
    'message' => '数据库连接失败',
    'suggestions' => [
        '检查数据库配置',
        '确认数据库服务运行正常',
        '查看错误日志获取详细信息'
    ],
    'isDebug' => true,
    'exception' => $exception,
    'requestId' => 'req_' . uniqid(),
    'timestamp' => now()->format('Y-m-d H:i:s')
])
```

#### 支持的状态码

| 状态码 | 表情 | 标题 | 默认消息 |
|--------|------|------|----------|
| 400 | 🤔 | 请求错误 | 请求参数错误，请检查输入 |
| 401 | 🔒 | 未授权 | 请先登录后再访问 |
| 403 | 🚫 | 禁止访问 | 您没有权限访问此页面 |
| 404 | 🤷 | 页面未找到 | 页面不存在或已被移除 |
| 405 | 🙅 | 方法不允许 | 请求方法不被允许 |
| 408 | ⏱️ | 请求超时 | 请求超时，请稍后重试 |
| 422 | 📝 | 无法处理 | 提交的数据验证失败 |
| 429 | 🐢 | 请求过多 | 请求过于频繁，请稍后再试 |
| 500 | 💥 | 服务器错误 | 服务器内部错误，请稍后重试 |
| 502 | 🔌 | 网关错误 | 网关错误，请稍后重试 |
| 503 | 🔧 | 服务不可用 | 服务暂时不可用，请稍后重试 |
| 504 | ⏳ | 网关超时 | 网关超时，请稍后重试 |

---

### 2. errors.fatal

**致命错误视图**，使用深色主题和 SVG 动画背景。

#### 特点

- 现代化的深色主题设计
- 浮动多边形 SVG 动画背景
- 渐变文字效果
- 适合严重错误场景

#### 参数

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `code` | int | 是 | HTTP 状态码（默认 500） |
| `message` | string | 否 | 错误消息 |
| `debugInfo` | string | 否 | 调试信息（HTML） |

#### 使用示例

```blade
@include('trace::errors.fatal', [
    'code' => 500,
    'message' => '系统发生严重错误',
    'debugInfo' => '<p>错误详情...</p>'
])
```

---

### 3. errors.generic

**通用错误页面**（向后兼容）。

#### 特点

- 与 `errors.error` 类似的布局
- 支持所有标准 HTTP 状态码
- 保持向后兼容

#### 使用示例

```blade
@include('trace::errors.generic', [
    'code' => 404,
    'message' => '页面未找到'
])
```

---

### 4. errors.minimal

**极简错误页面**，用于极端场景（如框架完全不可用）。

#### 特点

- 最简化的 HTML 结构
- 不依赖任何外部资源
- 适合作为最终兜底方案

#### 参数

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `code` | int | 否 | HTTP 状态码（默认 500） |
| `title` | string | 否 | 错误标题 |
| `message` | string | 否 | 错误消息 |

---

### 5. emergency

**紧急错误视图**（独立完整 HTML）。

#### 特点

- 不依赖 Laravel 布局系统
- 包含完整的 HTML 结构
- 用于 EmergencyRenderer 等极端场景
- 零依赖，即使框架崩溃也能正常显示

#### 参数

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `code` | int | 否 | HTTP 状态码（默认 500） |
| `title` | string | 否 | 错误标题 |
| `message` | string | 否 | 错误消息 |
| `emoji` | string | 否 | 表情符号 |
| `showDebug` | bool | 否 | 是否显示调试区域 |
| `showTrace` | bool | 否 | 是否显示堆栈跟踪 |
| `exception` | Throwable | 否 | 异常对象 |
| `requestId` | string | 否 | 请求ID |
| `timestamp` | string | 否 | 时间戳 |

---

## 调试视图

### debug

**调试信息视图**，用于显示详细的调试数据。

#### 特点

- 清晰的信息列表布局
- 支持代码块高亮显示
- 支持编辑器链接（点击打开 IDE）
- 响应式设计

#### 参数

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `title` | string | 是 | 页面标题 |
| `list` | array | 是 | 调试信息列表 |

#### list 项结构

```php
[
    'label' => '标签名称',
    'value' => '值内容',
    'type' => 'text|code|debug_file',  // 可选，默认 text
    'file' => '/path/to/file',          // type=debug_file 时需要
    'line' => 123,                      // type=debug_file 时需要
    'editor' => 'phpstorm',             // type=debug_file 时可选
]
```

#### 使用示例

```blade
@include('trace::debug', [
    'title' => '调试信息',
    'list' => [
        ['label' => 'URL', 'value' => request()->url()],
        ['label' => 'SQL', 'value' => $sql, 'type' => 'code'],
        [
            'label' => '文件',
            'value' => 'app/Models/User.php:45',
            'type' => 'debug_file',
            'file' => '/path/to/app/Models/User.php',
            'line' => 45
        ],
    ]
])
```

---

### trace-panel

**Trace 调试面板**，用于在页面底部显示调试工具栏。

#### 特点

- Tab 式导航
- 支持 SQL 分组显示
- 支持代码跳转链接
- 可折叠的 JSON 数据

#### 参数

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `trace` | array | 是 | Trace 数据数组 |

---

## 组件视图

### components.sql-group

**SQL 查询分组组件**。

#### 参数

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `name` | string | 否 | 分组名称 |
| `class` | string | 否 | CSS 类名 |
| `collapsed` | bool | 否 | 是否默认折叠 |
| `count` | int | 否 | SQL 数量 |
| `sqls` | array | 否 | SQL 列表 |

### components.trace-item

**Trace 数据项组件**。

#### 参数

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `data` | array | 是 | Trace 数据 |

#### data 结构

```php
[
    'file_path' => '/path/to/file.php',
    'line' => 123,
    'local' => '显示文本',
    'var' => $variable,  // 可选
    'editor' => 'phpstorm',  // 可选
]
```

### components.json-display

**JSON 数据显示组件**。

#### 参数

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `json` | string | 否 | JSON 字符串 |

---

## 布局视图

### layouts.error

**错误页面布局**。

#### 可覆盖的 Section

| Section | 说明 |
|---------|------|
| `title` | 页面标题 |
| `head-extra` | 额外的 head 内容（CSS 等） |
| `content` | 页面主体内容 |

#### 可用变量

| 变量 | 说明 |
|------|------|
| `requestId` | 自动生成的请求 ID |
| `timestamp` | 当前时间戳 |

#### 自定义视图继承示例

```blade
@extends('trace::layouts.error')

@section('title', '自定义错误')

@section('head-extra')
<style>
    .custom-style { color: red; }
</style>
@endsection

@section('content')
<div class="custom-style">
    <h1>自定义错误内容</h1>
    <p>错误代码: {{ $code }}</p>
</div>
@endsection
```

---

## 使用示例

### 在控制器中使用

```php
public function showError()
{
    return response()->view('trace::errors.error', [
        'code' => 404,
        'message' => '页面未找到',
    ], 404);
}
```

### 在异常处理器中使用

```php
public function render($request, Throwable $exception)
{
    $code = method_exists($exception, 'getStatusCode') 
        ? $exception->getStatusCode() 
        : 500;
    
    return response()->view('trace::errors.error', [
        'code' => $code,
        'message' => $exception->getMessage(),
        'exception' => $exception,
        'isDebug' => config('app.debug'),
    ], $code);
}
```

### 发布并自定义视图

```bash
# 发布视图到 resources/views/vendor/trace/
php artisan vendor:publish --tag=trace-views
```

发布后，您可以修改 `resources/views/vendor/trace/errors/error.blade.php` 来自定义错误页面。

---

## 视图优先级

当渲染错误页面时，Trace 包按以下优先级查找视图：

1. **用户自定义视图** - `resources/views/errors/{$code}.blade.php`
2. **Trace 包错误视图** - `resources/views/vendor/trace/errors/error.blade.php`（如果已发布）
3. **Trace 包默认视图** - `vendor/zxf/trace/src/Resources/views/errors/error.blade.php`
4. **Trace 包通用视图** - `trace::errors.generic`
5. **内置兜底模板** - 直接渲染 HTML

---

## 视图文件结构

```
resources/views/
├── vendor/trace/           # 发布的视图（可自定义）
│   ├── layouts/
│   │   └── error.blade.php
│   ├── errors/
│   │   ├── error.blade.php      # 统一错误视图
│   │   ├── fatal.blade.php      # 致命错误视图
│   │   ├── generic.blade.php    # 通用错误视图
│   │   └── minimal.blade.php    # 极简错误视图
│   ├── components/
│   │   ├── sql-group.blade.php
│   │   ├── trace-item.blade.php
│   │   └── json-display.blade.php
│   ├── debug.blade.php
│   ├── emergency.blade.php
│   ├── fatal.blade.php
│   └── trace-panel.blade.php
└── errors/                 # 用户自定义错误视图
    ├── 404.blade.php
    └── 500.blade.php
```

---

## 注意事项

1. **自定义视图时**，建议继承 `trace::layouts.error` 以保持一致的样式
2. **紧急视图**（emergency）是独立完整的 HTML，不依赖布局
3. **发布视图后**，更新包时不会自动覆盖您的自定义视图
4. **调试信息**仅在 `isDebug` 为 true 时显示，生产环境请确保关闭
