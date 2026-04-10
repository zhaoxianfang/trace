{{--
    统一错误视图 - 美化版
    支持所有 HTTP 状态码和多种显示模式
--}}
@php
$errorConfig = [
    400 => ['emoji' => '🤔', 'title' => '请求错误', 'color' => '#f59e0b'],
    401 => ['emoji' => '🔒', 'title' => '未授权', 'color' => '#8b5cf6'],
    403 => ['emoji' => '🚫', 'title' => '禁止访问', 'color' => '#ef4444'],
    404 => ['emoji' => '🌌', 'title' => '页面未找到', 'color' => '#06b6d4'],
    405 => ['emoji' => '🙅', 'title' => '方法不允许', 'color' => '#f59e0b'],
    408 => ['emoji' => '⏱️', 'title' => '请求超时', 'color' => '#f59e0b'],
    422 => ['emoji' => '📝', 'title' => '无法处理', 'color' => '#ec4899'],
    429 => ['emoji' => '🐢', 'title' => '请求过多', 'color' => '#f59e0b'],
    500 => ['emoji' => '💥', 'title' => '服务器错误', 'color' => '#ef4444'],
    502 => ['emoji' => '🔌', 'title' => '网关错误', 'color' => '#ef4444'],
    503 => ['emoji' => '🔧', 'title' => '服务不可用', 'color' => '#f59e0b'],
    504 => ['emoji' => '⏳', 'title' => '网关超时', 'color' => '#f59e0b'],
];

$config = $errorConfig[$code] ?? ['emoji' => '⚠️', 'title' => '系统错误', 'color' => '#6b7280'];
$themeColor = $config['color'];

$mode = $mode ?? 'full';
$emoji = $emoji ?? $config['emoji'];
$title = $title ?? $config['title'];
$displayMessage = $message ?? $config['message'];
$suggestionList = $suggestions ?? [];
$showRequestId = $requestId ?? substr(md5(uniqid()), 0, 12);
$showTimestamp = $timestamp ?? date('Y-m-d H:i:s');

$showEmoji = $mode !== 'minimal';
$showSuggestions = $mode === 'full' && !empty($suggestionList);
$showDebug = ($isDebug ?? false) && isset($exception) && $mode !== 'minimal';
$showActions = $mode !== 'minimal';

$showRefresh = in_array($code, [500, 502, 503, 504, 408, 429]);
$showBack = in_array($code, [400, 401, 403, 404, 405, 422]);
@endphp

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} - {{ $code }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            position: relative;
            overflow: hidden;
        }

        /* 动态网格背景 */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 200%;
            height: 200%;
            background-image: 
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: gridMove 20s linear infinite;
            pointer-events: none;
        }

        @keyframes gridMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(-50px, -50px); }
        }

        /* 发光光晕效果 */
        .glow {
            position: fixed;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.3;
            pointer-events: none;
            animation: glowPulse 4s ease-in-out infinite;
        }

        .glow-1 {
            top: -200px;
            right: -200px;
            background: {{ $themeColor }};
        }

        .glow-2 {
            bottom: -200px;
            left: -200px;
            background: #667eea;
            animation-delay: 2s;
        }

        @keyframes glowPulse {
            0%, 100% { transform: scale(1); opacity: 0.3; }
            50% { transform: scale(1.2); opacity: 0.5; }
        }

        /* 浮动粒子 */
        .particles {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
            animation: float 15s infinite;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% {
                transform: translateY(-100vh) rotate(720deg);
                opacity: 0;
            }
        }

        /* 主容器 - 毛玻璃效果 */
        .container {
            position: relative;
            z-index: 10;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            max-width: 600px;
            width: 100%;
            padding: 50px 40px;
            text-align: center;
            animation: containerAppear 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes containerAppear {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        /* 状态码圆环 */
        .code-ring {
            position: relative;
            width: 140px;
            height: 140px;
            margin: 0 auto 30px;
        }

        .code-ring svg {
            transform: rotate(-90deg);
        }

        .code-ring-bg {
            fill: none;
            stroke: rgba(255,255,255,0.1);
            stroke-width: 4;
        }

        .code-ring-progress {
            fill: none;
            stroke: {{ $themeColor }};
            stroke-width: 4;
            stroke-linecap: round;
            stroke-dasharray: 377;
            stroke-dashoffset: 377;
            animation: ringProgress 1s ease-out forwards;
            filter: drop-shadow(0 0 10px {{ $themeColor }});
        }

        @keyframes ringProgress {
            to { stroke-dashoffset: 0; }
        }

        .code {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 48px;
            font-weight: 800;
            color: {{ $themeColor }};
            text-shadow: 0 0 30px {{ $themeColor }}80;
        }

        /* 表情动画 */
        .emoji {
            font-size: 70px;
            margin-bottom: 20px;
            display: inline-block;
            animation: emojiBounce 2s ease-in-out infinite;
            filter: drop-shadow(0 10px 20px rgba(0,0,0,0.3));
        }

        @keyframes emojiBounce {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            25% { transform: translateY(-10px) rotate(-5deg); }
            75% { transform: translateY(-5px) rotate(5deg); }
        }

        .title {
            font-size: 28px;
            color: #fff;
            margin-bottom: 15px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .message {
            font-size: 16px;
            color: rgba(255,255,255,0.6);
            margin-bottom: 35px;
            line-height: 1.7;
        }

        /* 按钮样式 */
        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 28px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, {{ $themeColor }} 0%, {{ $themeColor }}dd 100%);
            color: white;
            box-shadow: 0 4px 15px {{ $themeColor }}40;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px {{ $themeColor }}60;
        }

        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.9);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.15);
            transform: translateY(-2px);
        }

        /* 建议列表 */
        .error-list {
            text-align: left;
            background: rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 20px 25px;
            margin-top: 30px;
        }

        .error-list h3 {
            color: {{ $themeColor }};
            font-size: 14px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .error-list ul {
            list-style: none;
            color: rgba(255,255,255,0.7);
            font-size: 13px;
        }

        .error-list li {
            margin-bottom: 10px;
            padding-left: 20px;
            position: relative;
            line-height: 1.5;
        }

        .error-list li::before {
            content: "→";
            position: absolute;
            left: 0;
            color: {{ $themeColor }};
        }

        /* 调试信息 */
        .debug-info {
            margin-top: 30px;
            text-align: left;
            background: rgba(0,0,0,0.3);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .debug-header {
            background: rgba(255,255,255,0.05);
            padding: 15px 20px;
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .debug-type {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
            background: rgba(255,255,255,0.1);
            padding: 4px 10px;
            border-radius: 20px;
        }

        .debug-content {
            padding: 20px;
            color: rgba(255,255,255,0.8);
            font-family: 'JetBrains Mono', Consolas, monospace;
            font-size: 12px;
            line-height: 1.8;
        }

        .debug-item {
            margin-bottom: 12px;
        }

        .debug-label {
            color: {{ $themeColor }};
            font-weight: 600;
            margin-right: 8px;
        }

        .debug-trace {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 200px;
            overflow-y: auto;
            color: rgba(255,255,255,0.5);
            font-size: 11px;
        }

        /* 底部信息 */
        .meta-info {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 12px;
            color: rgba(255,255,255,0.4);
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* 响应式 */
        @media (max-width: 480px) {
            .container {
                padding: 35px 25px;
            }
            
            .code-ring {
                width: 110px;
                height: 110px;
            }
            
            .code {
                font-size: 36px;
            }
            
            .emoji {
                font-size: 50px;
            }
            
            .title {
                font-size: 22px;
            }
        }

        /* 紧凑模式 */
        .container.compact {
            padding: 40px 30px;
        }
        
        .container.compact .emoji {
            font-size: 50px;
        }

        /* 极简模式 */
        .container.minimal {
            padding: 50px 35px;
            max-width: 400px;
        }
        
        .container.minimal .code-ring {
            width: 100px;
            height: 100px;
        }
        
        .container.minimal .code {
            font-size: 36px;
        }
    </style>
</head>
<body>
    <!-- 发光背景 -->
    <div class="glow glow-1"></div>
    <div class="glow glow-2"></div>

    <!-- 浮动粒子 -->
    <div class="particles" id="particles"></div>

    <div class="container {{ $mode }}">
        <!-- 状态码圆环 -->
        <div class="code-ring">
            <svg width="140" height="140">
                <circle class="code-ring-bg" cx="70" cy="70" r="60"/>
                <circle class="code-ring-progress" cx="70" cy="70" r="60"/>
            </svg>
            <div class="code">{{ $code }}</div>
        </div>

        @if($showEmoji)
            <span class="emoji">{{ $emoji }}</span>
        @endif

        <h1 class="title">{{ $title }}</h1>
        <p class="message">{{ $displayMessage }}</p>

        @if($showActions)
            <div class="actions">
                <a href="/" class="btn btn-primary">
                    <span>🏠</span> 返回首页
                </a>
                @if($showBack)
                    <button onclick="history.back()" class="btn btn-secondary">
                        <span>←</span> 返回上一页
                    </button>
                @endif
                @if($showRefresh)
                    <button onclick="location.reload()" class="btn btn-secondary">
                        <span>🔄</span> 刷新页面
                    </button>
                @endif
            </div>
        @endif

        @if($showSuggestions)
            <div class="error-list">
                <h3>
                    <span>💡</span>
                    {{ $code >= 500 ? '建议操作' : '可能的原因' }}
                </h3>
                <ul>
                    @foreach($suggestionList as $suggestion)
                        <li>{{ $suggestion }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($showDebug)
            <div class="debug-info">
                <div class="debug-header">
                    <span>🔧 调试信息</span>
                    <span class="debug-type">{{ get_class($exception) }}</span>
                </div>
                <div class="debug-content">
                    <div class="debug-item">
                        <span class="debug-label">消息:</span>
                        <span style="color: #ff7b7b;">{{ $exception->getMessage() }}</span>
                    </div>
                    <div class="debug-item">
                        <span class="debug-label">位置:</span>
                        {{ $exception->getFile() }}:{{ $exception->getLine() }}
                    </div>
                    @if(method_exists($exception, 'getTraceAsString'))
                        <div class="debug-trace">{{ $exception->getTraceAsString() }}</div>
                    @endif
                </div>
            </div>
        @endif

        <div class="meta-info">
            <div class="meta-item">
                <span>🆔</span> {{ $showRequestId }}
            </div>
            @if($mode !== 'minimal')
                <div class="meta-item">
                    <span>🕐</span> {{ $showTimestamp }}
                </div>
            @endif
        </div>
    </div>

    <script>
        // 生成浮动粒子
        const particlesContainer = document.getElementById('particles');
        for (let i = 0; i < 30; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
            particle.style.animationDelay = Math.random() * 15 + 's';
            particle.style.opacity = Math.random() * 0.5 + 0.2;
            particlesContainer.appendChild(particle);
        }
    </script>
</body>
</html>
