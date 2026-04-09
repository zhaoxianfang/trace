@extends('trace::layouts.error')

@section('title', $title ?? '系统错误')

@php
$emojis = [
    400 => '🤔',
    401 => '🔒',
    403 => '🚫',
    404 => '🤷',
    405 => '🙅',
    408 => '⏱️',
    422 => '📝',
    429 => '🐢',
    500 => '💥',
    502 => '🔌',
    503 => '🔧',
    504 => '⏳',
];
$emoji = $emojis[$code] ?? '⚠️';

$titles = [
    400 => '请求错误',
    401 => '未授权',
    403 => '禁止访问',
    404 => '页面未找到',
    405 => '方法不允许',
    408 => '请求超时',
    422 => '无法处理',
    429 => '请求过多',
    500 => '服务器错误',
    502 => '网关错误',
    503 => '服务不可用',
    504 => '网关超时',
];
$title = $titles[$code] ?? '系统错误';

$messages = [
    400 => '请求参数错误，请检查输入',
    401 => '请先登录后再访问',
    403 => '您没有权限访问此页面',
    404 => '页面不存在或已被移除',
    405 => '请求方法不被允许',
    408 => '请求超时，请稍后重试',
    422 => '提交的数据验证失败',
    429 => '请求过于频繁，请稍后再试',
    500 => '服务器内部错误，请稍后重试',
    502 => '网关错误，请稍后重试',
    503 => '服务暂时不可用，请稍后重试',
    504 => '网关超时，请稍后重试',
];
$publicMessage = $messages[$code] ?? '系统发生错误，请稍后重试或联系管理员';
@endphp

@section('content')
    <span class="emoji">{{ $emoji }}</span>
    <div class="code">{{ $code }}</div>
    <h1 class="title">{{ $title }}</h1>
    <p class="message">{{ $isDebug ? $message : $publicMessage }}</p>

    <div class="actions">
        <a href="/" class="btn btn-primary">返回首页</a>
        <button onclick="location.reload()" class="btn btn-secondary">刷新页面</button>
        <button onclick="history.back()" class="btn btn-secondary">返回上一页</button>
    </div>

    @if($isDebug && isset($exception))
        <div class="debug-info">
            <div class="debug-header">调试信息</div>
            <div class="debug-content">
                <div class="debug-item"><span class="debug-label">类型:</span> {{ get_class($exception) }}</div>
                <div class="debug-item"><span class="debug-label">消息:</span> {{ $exception->getMessage() }}</div>
                <div class="debug-item"><span class="debug-label">文件:</span> {{ $exception->getFile() }}:{{ $exception->getLine() }}</div>
                <div class="debug-trace">{{ $exception->getTraceAsString() }}</div>
            </div>
        </div>
    @endif
@endsection
