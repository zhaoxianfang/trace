{{--
    紧急错误视图
    用于 EmergencyRenderer 和 FallbackExceptionHandler 降级场景
    特点：不依赖任何外部变量，提供安全的默认值
--}}
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? '系统错误' }} - {{ $code ?? 500 }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
            text-align: center;
            animation: slideIn 0.5s ease-out;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .emoji {
            font-size: 70px;
            margin-bottom: 15px;
            display: block;
            animation: bounce 2s infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .code {
            font-size: 72px;
            font-weight: bold;
            color: #e53e3e;
            line-height: 1;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        .title {
            font-size: 24px;
            color: #2d3748;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .message {
            font-size: 16px;
            color: #718096;
            margin-bottom: 30px;
            line-height: 1.6;
            word-break: break-word;
        }
        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-block;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #edf2f7;
            color: #4a5568;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        .debug-info {
            margin-top: 30px;
            text-align: left;
            background: #1a202c;
            border-radius: 10px;
            overflow: hidden;
        }
        .debug-header {
            background: #2d3748;
            padding: 12px 20px;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
        }
        .debug-content {
            padding: 20px;
            color: #e2e8f0;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 13px;
            line-height: 1.6;
            overflow-x: auto;
        }
        .debug-item {
            margin-bottom: 10px;
        }
        .debug-label {
            color: #68d391;
            font-weight: 600;
        }
        .debug-trace {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #4a5568;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 300px;
            overflow-y: auto;
        }
        .meta-info {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #a0aec0;
        }
        @media (max-width: 480px) {
            .container { padding: 30px 20px; }
            .code { font-size: 56px; }
            .emoji { font-size: 56px; }
            .title { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        @php
            $emojis = [
                400 => '🤔', 401 => '🔒', 403 => '🚫', 404 => '🤷',
                405 => '🙅', 408 => '⏱️', 422 => '📝', 429 => '🐢',
                500 => '💥', 502 => '🔌', 503 => '🔧', 504 => '⏳',
            ];
            $errorEmoji = $emojis[$code ?? 500] ?? '⚠️';
        @endphp
        
        <span class="emoji">{{ $emoji ?? $errorEmoji }}</span>
        <div class="code">{{ $code ?? 500 }}</div>
        <h1 class="title">{{ $title ?? '系统错误' }}</h1>
        <p class="message">{{ $message ?? '系统发生错误，请稍后重试或联系管理员' }}</p>

        <div class="actions">
            <a href="/" class="btn btn-primary">返回首页</a>
            <button onclick="location.reload()" class="btn btn-secondary">刷新页面</button>
        </div>

        @if(($showDebug ?? false) && isset($exception))
            <div class="debug-info">
                <div class="debug-header">调试信息</div>
                <div class="debug-content">
                    <div class="debug-item"><span class="debug-label">类型:</span> {{ get_class($exception) }}</div>
                    <div class="debug-item"><span class="debug-label">消息:</span> {{ $exception->getMessage() }}</div>
                    <div class="debug-item"><span class="debug-label">文件:</span> {{ $exception->getFile() }}:{{ $exception->getLine() }}</div>
                    @if($showTrace ?? false)
                        <div class="debug-trace">{{ $exception->getTraceAsString() }}</div>
                    @endif
                </div>
            </div>
        @endif

        @if($requestId ?? false)
            <div class="meta-info">
                请求ID: {{ $requestId }}
                @if($timestamp ?? false)
                    | 时间: {{ $timestamp }}
                @endif
            </div>
        @endif
    </div>
</body>
</html>
