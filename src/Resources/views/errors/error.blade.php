{{--
    通用 HTTP 错误视图
    支持所有 HTTP 状态码：400, 401, 403, 404, 405, 408, 422, 429, 500, 502, 503, 504
    参数：
    - $code: HTTP 状态码 (必需)
    - $message: 自定义错误消息 (可选)
    - $suggestions: 建议/原因列表 (可选数组)
    - $isDebug: 是否显示调试信息 (可选)
    - $exception: 异常对象 (可选，用于调试)
    - $requestId: 请求ID (可选)
    - $timestamp: 时间戳 (可选)
--}}
@extends('trace::layouts.error')

@php
// 错误配置映射
$errorConfig = [
    400 => [
        'emoji' => '🤔',
        'title' => '请求错误',
        'message' => '请求参数错误，请检查输入',
        'suggestions' => [
            '请求参数格式不正确',
            '缺少必需的参数',
            '请求体格式错误',
        ],
    ],
    401 => [
        'emoji' => '🔒',
        'title' => '未授权',
        'message' => '请先登录后再访问',
        'suggestions' => [
            '登录已过期，请重新登录',
            '未提供有效的认证信息',
            '账号权限不足',
        ],
    ],
    403 => [
        'emoji' => '🚫',
        'title' => '禁止访问',
        'message' => '您没有权限访问此页面',
        'suggestions' => [
            '未登录或登录已过期',
            '账号权限不足',
            '访问了受限资源',
        ],
    ],
    404 => [
        'emoji' => '🤷',
        'title' => '页面未找到',
        'message' => '页面不存在或已被移除',
        'suggestions' => [
            '页面地址输入错误',
            '页面已被删除或移动',
            '页面链接已过期',
        ],
    ],
    405 => [
        'emoji' => '🙅',
        'title' => '方法不允许',
        'message' => '请求方法不被允许',
        'suggestions' => [
            '使用了错误的 HTTP 方法',
            '该端点不支持此操作',
            '请检查 API 文档',
        ],
    ],
    408 => [
        'emoji' => '⏱️',
        'title' => '请求超时',
        'message' => '请求超时，请稍后重试',
        'suggestions' => [
            '网络连接不稳定',
            '服务器响应缓慢',
            '请求处理时间过长',
        ],
    ],
    422 => [
        'emoji' => '📝',
        'title' => '无法处理',
        'message' => '提交的数据验证失败',
        'suggestions' => [
            '表单数据格式不正确',
            '必填字段未填写',
            '数据不符合验证规则',
        ],
    ],
    429 => [
        'emoji' => '🐢',
        'title' => '请求过多',
        'message' => '请求过于频繁，请稍后再试',
        'suggestions' => [
            '请求频率超过限制',
            '请等待一段时间后重试',
            '考虑优化请求策略',
        ],
    ],
    500 => [
        'emoji' => '💥',
        'title' => '服务器错误',
        'message' => '服务器内部错误，请稍后重试',
        'suggestions' => [
            '服务器遇到意外错误',
            '请刷新页面重试',
            '如果问题持续，请联系管理员',
        ],
    ],
    502 => [
        'emoji' => '🔌',
        'title' => '网关错误',
        'message' => '网关错误，请稍后重试',
        'suggestions' => [
            '上游服务器无响应',
            '网关配置错误',
            '请稍后再次访问',
        ],
    ],
    503 => [
        'emoji' => '🔧',
        'title' => '服务不可用',
        'message' => '服务暂时不可用，请稍后重试',
        'suggestions' => [
            '系统正在维护升级',
            '服务器负载过高',
            '请稍后再次访问',
        ],
    ],
    504 => [
        'emoji' => '⏳',
        'title' => '网关超时',
        'message' => '网关超时，请稍后重试',
        'suggestions' => [
            '上游服务器响应超时',
            '网络连接问题',
            '请稍后再次访问',
        ],
    ],
];

// 获取当前错误配置
$config = $errorConfig[$code] ?? [
    'emoji' => '⚠️',
    'title' => '系统错误',
    'message' => '系统发生错误，请稍后重试或联系管理员',
    'suggestions' => [],
];

// 使用传入的参数或默认值
$emoji = $emoji ?? $config['emoji'];
$title = $title ?? $config['title'];
$displayMessage = $message ?? $config['message'];
$suggestionList = $suggestions ?? $config['suggestions'];

// 根据状态码确定操作按钮
$showRefresh = in_array($code, [500, 502, 503, 504, 408, 429]);
$showBack = in_array($code, [400, 401, 403, 404, 405, 422]);
@endphp

@section('title', $title)

@section('content')
<span class="emoji">{{ $emoji }}</span>
<div class="code">{{ $code }}</div>
<h1 class="title">{{ $title }}</h1>
<p class="message">{{ $displayMessage }}</p>

<div class="actions">
    <a href="/" class="btn btn-primary">返回首页</a>
    @if($showBack)
        <button onclick="history.back()" class="btn btn-secondary">返回上一页</button>
    @endif
    @if($showRefresh)
        <button onclick="location.reload()" class="btn btn-secondary">刷新页面</button>
    @endif
</div>

@if(!empty($suggestionList))
<div class="error-list">
    <h3>{{ $code >= 500 ? '建议操作' : '可能的原因' }}：</h3>
    <ul>
        @foreach($suggestionList as $suggestion)
            <li>{{ $suggestion }}</li>
        @endforeach
    </ul>
</div>
@endif

@if(($isDebug ?? false) && isset($exception))
<div class="debug-info">
    <div class="debug-header">
        <span>调试信息</span>
        <span style="float: right; font-weight: normal; font-size: 12px; color: #a0aec0;">
            {{ get_class($exception) }}
        </span>
    </div>
    <div class="debug-content">
        <div class="debug-item">
            <span class="debug-label">消息:</span>
            <span style="color: #fc8181;">{{ $exception->getMessage() }}</span>
        </div>
        <div class="debug-item">
            <span class="debug-label">文件:</span>
            {{ $exception->getFile() }}:{{ $exception->getLine() }}
        </div>
        @if(method_exists($exception, 'getTraceAsString'))
        <div class="debug-trace">{{ $exception->getTraceAsString() }}</div>
        @endif
    </div>
</div>
@endif
@endsection
