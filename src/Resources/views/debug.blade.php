<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? '系统错误/调试' }} - {{ config('app.name', 'Trace Debug') }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
            min-height: 100vh;
            padding: 20px;
            background: linear-gradient(-45deg, #0f0c29, #302b63, #24243e, #1a1a2e, #16213e);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            position: relative;
            overflow-x: hidden;
        }

        /* 动态背景动画 */
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* 粒子背景效果 */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 219, 255, 0.2) 0%, transparent 40%);
            pointer-events: none;
            z-index: 0;
        }

        /* 浮动气泡效果 */
        .bubbles {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .bubble {
            position: absolute;
            bottom: -100px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            animation: float 20s infinite ease-in;
        }

        .bubble:nth-child(1) { width: 40px; height: 40px; left: 10%; animation-duration: 15s; animation-delay: 0s; }
        .bubble:nth-child(2) { width: 20px; height: 20px; left: 20%; animation-duration: 25s; animation-delay: 2s; }
        .bubble:nth-child(3) { width: 50px; height: 50px; left: 35%; animation-duration: 18s; animation-delay: 4s; }
        .bubble:nth-child(4) { width: 80px; height: 80px; left: 50%; animation-duration: 22s; animation-delay: 1s; }
        .bubble:nth-child(5) { width: 35px; height: 35px; left: 65%; animation-duration: 20s; animation-delay: 3s; }
        .bubble:nth-child(6) { width: 45px; height: 45px; left: 80%; animation-duration: 17s; animation-delay: 5s; }
        .bubble:nth-child(7) { width: 25px; height: 25px; left: 90%; animation-duration: 23s; animation-delay: 2s; }

        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 0.8;
            }
            100% {
                transform: translateY(-100vh) rotate(720deg);
                opacity: 0;
            }
        }

        /* 主容器 - 毛玻璃效果 */
        .debug-container {
            position: relative;
            z-index: 1;
            width: 90%;
            max-width: 1200px;
            min-height: calc(100vh - 100px);
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            padding: 40px;
            animation: containerFadeIn 0.8s ease-out;
        }

        @keyframes containerFadeIn {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* 标题样式 */
        .debug-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .debug-icon {
            font-size: 60px;
            margin-bottom: 15px;
            display: inline-block;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .debug-title {
            color: #ff6b6b;
            font-size: 32px;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(255, 107, 107, 0.3);
            letter-spacing: -0.5px;
        }

        .debug-subtitle {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            margin-top: 8px;
        }

        /* 信息列表 */
        .info-list {
            list-style: none;
            padding: 0;
            display: grid;
            gap: 16px;
        }

        /* 信息项卡片 */
        .info-item {
            display: flex;
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            align-items: flex-start;
            transition: all 0.3s ease;
            animation: itemSlideIn 0.5s ease-out forwards;
            opacity: 0;
        }

        .info-item:nth-child(1) { animation-delay: 0.1s; }
        .info-item:nth-child(2) { animation-delay: 0.2s; }
        .info-item:nth-child(3) { animation-delay: 0.3s; }
        .info-item:nth-child(4) { animation-delay: 0.4s; }
        .info-item:nth-child(5) { animation-delay: 0.5s; }

        @keyframes itemSlideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .info-item:hover {
            background: rgba(0, 0, 0, 0.3);
            border-color: rgba(78, 205, 196, 0.3);
            transform: translateX(5px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .info-label {
            font-weight: 600;
            color: #4ecdc4;
            min-width: 100px;
            padding-right: 20px;
            flex-shrink: 0;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            flex: 1;
            word-break: break-word;
            color: #e0e0e0;
            overflow: auto;
            font-size: 14px;
            line-height: 1.6;
        }

        /* 代码块样式 - 现代深色主题 */
        .info-value pre {
            background: #0d1117;
            color: #c9d1d9;
            border-radius: 12px;
            padding: 20px;
            overflow-x: auto;
            font-family: 'JetBrains Mono', 'Fira Code', 'SF Mono', Consolas, Monaco, monospace;
            font-size: 13px;
            line-height: 1.6;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
            margin: 8px 0 0 0;
        }

        .info-value pre code {
            display: block;
        }

        /* 错误行高亮 - 霓虹效果 */
        .error-line-code {
            background: linear-gradient(90deg, rgba(243, 139, 168, 0.25) 0%, transparent 100%);
            color: #ff7b7b;
            display: block;
            font-weight: 600;
            border-left: 3px solid #ff6b6b;
            margin: 0 -20px;
            padding: 4px 20px;
            text-shadow: 0 0 10px rgba(255, 107, 107, 0.5);
        }

        /* 普通代码行 */
        .info-value pre code:not(.error-line-code) {
            color: #c9d1d9;
        }

        /* 文件链接 */
        .phpdebugbar-link {
            color: #4ecdc4;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border-bottom: 1px dashed rgba(78, 205, 196, 0.3);
        }

        .phpdebugbar-link:hover {
            color: #6ee7df;
            border-bottom-color: #6ee7df;
            text-shadow: 0 0 10px rgba(78, 205, 196, 0.5);
        }

        /* 页脚 */
        .error-footer {
            position: relative;
            z-index: 1;
            margin-top: 40px;
            padding: 20px;
            text-align: center;
            color: rgba(255, 255, 255, 0.4);
            font-size: 12px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .error-footer a {
            color: #4ecdc4;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .error-footer a:hover {
            color: #6ee7df;
        }

        /* 滚动条美化 */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .debug-container {
                width: 95%;
                padding: 24px;
            }
            
            .debug-title {
                font-size: 24px;
            }

            .info-item {
                flex-direction: column;
                padding: 16px;
            }

            .info-label {
                margin-bottom: 10px;
                min-width: auto;
            }

            .info-value pre {
                padding: 12px;
                font-size: 12px;
            }
        }

        /* 高亮动画 */
        @keyframes highlightPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(255, 107, 107, 0.4); }
            50% { box-shadow: 0 0 20px 5px rgba(255, 107, 107, 0.2); }
        }

        .info-item:has(.error-line-code) {
            animation: itemSlideIn 0.5s ease-out forwards, highlightPulse 2s ease-in-out infinite;
            border-color: rgba(255, 107, 107, 0.3);
        }
    </style>
</head>
<body>
    <!-- 浮动气泡背景 -->
    <div class="bubbles">
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
    </div>

    <div class="debug-container">
        <div class="debug-header">
            <div class="debug-icon">🐛</div>
            <h1 class="debug-title">{{ $title ?? '系统错误/调试' }}</h1>
            <div class="debug-subtitle">Trace Debug Information</div>
        </div>

        <ul class="info-list">
            @foreach($list as $item)
                <li class="info-item">
                    <span class="info-label">{{ $item['label'] }}</span>
                    <div class="info-value">
                        @if($item['type'] === 'code_html')
                            {{-- 已包含 HTML 标签的代码内容（如错误行高亮） --}}
                            <pre>{!! $item['value'] !!}</pre>
                        @elseif($item['type'] === 'code')
                            <pre><code>{{ $item['value'] }}</code></pre>
                        @elseif($item['type'] === 'debug_file')
                            <a href="{{ $item['editor'] ?? 'phpstorm' }}://open?file={{ urlencode($item['file'] ?? '') }}&amp;line={{ $item['line'] ?? 1 }}" class="phpdebugbar-link">
                                {{ $item['value'] }}
                            </a>
                        @else
                            {{ is_string($item['value']) ? $item['value'] : var_export($item['value'], true) }}
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>

    <footer class="error-footer">
        &copy; {{ date('Y') }} {{ config('app.name', 'Trace Debug') }} 版权所有.
        <span style="margin-left: 20px;">
            本页面由 <a href="http://www.yoc.cn/" target="_blank">Trace Debug</a> 提供支持
        </span>
    </footer>
</body>
</html>
