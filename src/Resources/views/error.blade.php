<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Error' }} - {{ $code ?? 500 }}</title>
    <style>
        /* ===== 基础重置 - 仅影响本页面 ===== */
        .trace-error-page *, .trace-error-page *::before, .trace-error-page *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        /* ===== 页面容器 ===== */
        .trace-error-page {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        /* ===== 动态背景特效 ===== */
        .trace-error-page .bg-animation {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }
        
        .trace-error-page .bg-animation span {
            position: absolute;
            display: block;
            width: 20px;
            height: 20px;
            background: rgba(255, 255, 255, 0.1);
            animation: float-up 25s linear infinite;
            bottom: -150px;
            border-radius: 50%;
        }
        
        .trace-error-page .bg-animation span:nth-child(1) { left: 25%; width: 80px; height: 80px; animation-delay: 0s; }
        .trace-error-page .bg-animation span:nth-child(2) { left: 10%; width: 20px; height: 20px; animation-delay: 2s; animation-duration: 12s; }
        .trace-error-page .bg-animation span:nth-child(3) { left: 70%; width: 20px; height: 20px; animation-delay: 4s; }
        .trace-error-page .bg-animation span:nth-child(4) { left: 40%; width: 60px; height: 60px; animation-delay: 0s; animation-duration: 18s; }
        .trace-error-page .bg-animation span:nth-child(5) { left: 65%; width: 20px; height: 20px; animation-delay: 0s; }
        .trace-error-page .bg-animation span:nth-child(6) { left: 75%; width: 110px; height: 110px; animation-delay: 3s; }
        .trace-error-page .bg-animation span:nth-child(7) { left: 35%; width: 150px; height: 150px; animation-delay: 7s; }
        .trace-error-page .bg-animation span:nth-child(8) { left: 50%; width: 25px; height: 25px; animation-delay: 15s; animation-duration: 45s; }
        .trace-error-page .bg-animation span:nth-child(9) { left: 20%; width: 15px; height: 15px; animation-delay: 2s; animation-duration: 35s; }
        .trace-error-page .bg-animation span:nth-child(10) { left: 85%; width: 150px; height: 150px; animation-delay: 0s; animation-duration: 11s; }
        
        @keyframes float-up {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; border-radius: 0; }
            100% { transform: translateY(-1000px) rotate(720deg); opacity: 0; border-radius: 50%; }
        }
        
        /* ===== 粒子效果 ===== */
        .trace-error-page .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
        }
        
        .trace-error-page .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            animation: particle-float 15s infinite;
        }
        
        @keyframes particle-float {
            0%, 100% { transform: translateY(100vh) scale(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100vh) scale(1); opacity: 0; }
        }
        
        /* ===== 主容器 ===== */
        .trace-error-page .container {
            position: relative;
            z-index: 10;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3), 
                        0 0 0 1px rgba(255, 255, 255, 0.2) inset;
            max-width: 650px;
            width: 100%;
            padding: 50px 40px;
            text-align: center;
            animation: slide-up 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        @keyframes slide-up {
            from { opacity: 0; transform: translateY(40px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        /* ===== 状态码样式 ===== */
        .trace-error-page .status-code {
            font-size: 120px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: pulse 2s ease-in-out infinite;
            text-shadow: none;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.85; transform: scale(0.98); }
        }
        
        /* 不同状态码的颜色主题 */
        .trace-error-page .status-code.error-4xx {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .trace-error-page .status-code.error-5xx {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .trace-error-page .status-code.error-3xx {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* ===== 标题和消息 ===== */
        .trace-error-page h1 {
            font-size: 28px;
            color: #1a202c;
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        .trace-error-page .message {
            font-size: 16px;
            color: #4a5568;
            margin-bottom: 30px;
            line-height: 1.7;
        }
        
        /* ===== 操作按钮 ===== */
        .trace-error-page .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        
        .trace-error-page .btn {
            padding: 14px 32px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .trace-error-page .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .trace-error-page .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
        }
        
        .trace-error-page .btn-secondary {
            background: #edf2f7;
            color: #4a5568;
        }
        
        .trace-error-page .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }
        
        /* ===== 调试信息区域 ===== */
        .trace-error-page .debug-section {
            margin-top: 30px;
            text-align: left;
            background: #1a1a2e;
            border-radius: 16px;
            overflow: hidden;
        }
        
        .trace-error-page .debug-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .trace-error-page .debug-content {
            padding: 20px;
            color: #eaeaea;
            font-family: 'JetBrains Mono', 'Fira Code', Consolas, Monaco, monospace;
            font-size: 13px;
            line-height: 1.6;
        }
        
        .trace-error-page .debug-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #2d2d44;
        }
        
        .trace-error-page .debug-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .trace-error-page .debug-label {
            color: #a0aec0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .trace-error-page .debug-value {
            color: #68d391;
            word-break: break-all;
        }
        
        .trace-error-page .debug-value pre {
            background: #16162a;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            margin-top: 8px;
            color: #eaeaea;
            font-size: 12px;
            line-height: 1.5;
        }
        
        .trace-error-page .debug-value code {
            color: #f687b3;
        }
        
        /* 错误行高亮 */
        .trace-error-page .error-line-code {
            background-color: rgba(245, 101, 101, 0.2);
            color: #fc8181;
            display: block;
            border-left: 3px solid #f56565;
            padding-left: 12px;
            margin-left: -15px;
        }
        
        /* ===== 元信息 ===== */
        .trace-error-page .meta-info {
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #718096;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .trace-error-page .meta-info span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        /* ===== 响应式 ===== */
        @media (max-width: 480px) {
            .trace-error-page .container {
                padding: 35px 25px;
                border-radius: 20px;
            }
            
            .trace-error-page .status-code {
                font-size: 80px;
            }
            
            .trace-error-page h1 {
                font-size: 22px;
            }
            
            .trace-error-page .actions {
                flex-direction: column;
            }
            
            .trace-error-page .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* ===== 暗色模式支持 ===== */
        @media (prefers-color-scheme: dark) {
            .trace-error-page {
                background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            }
            
            .trace-error-page .container {
                background: rgba(30, 30, 46, 0.95);
            }
            
            .trace-error-page h1 {
                color: #e2e8f0;
            }
            
            .trace-error-page .message {
                color: #a0aec0;
            }
            
            .trace-error-page .meta-info {
                border-color: #2d3748;
                color: #718096;
            }
        }
    </style>
</head>
<body>
    <div class="trace-error-page">
        <!-- 动态背景 -->
        <div class="bg-animation">
            @for($i = 0; $i < 10; $i++)
                <span></span>
            @endfor
        </div>
        
        <!-- 粒子效果 -->
        <div class="particles" id="particles"></div>
        
        <!-- 主容器 -->
        <div class="container">
            @php
                $codeVal = $code ?? 500;
                $codeClass = $codeVal >= 500 ? 'error-5xx' : ($codeVal >= 400 ? 'error-4xx' : 'error-3xx');
                $titleVal = $title ?? (isset($exception) ? class_basename(get_class($exception)) : 'Error');
                $messageVal = $message ?? 'An error occurred while processing your request.';
                $isDebug = $isDebug ?? config('app.debug', false);
                $requestId = $requestId ?? substr(md5(uniqid()), 0, 12);
                $timestamp = $timestamp ?? date('Y-m-d H:i:s');
            @endphp
            
            <div class="status-code {{ $codeClass }}">{{ $codeVal }}</div>
            <h1>{{ $titleVal }}</h1>
            <p class="message">{{ $messageVal }}</p>
            
            <div class="actions">
                <a href="{{ url('/') }}" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    返回首页
                </a>
                <button onclick="location.reload()" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                    刷新页面
                </button>
            </div>
            
            @if($isDebug && isset($list) && is_array($list))
                <div class="debug-section">
                    <div class="debug-header">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                            <line x1="8" y1="21" x2="16" y2="21"></line>
                            <line x1="12" y1="17" x2="12" y2="21"></line>
                        </svg>
                        Debug Information
                    </div>
                    <div class="debug-content">
                        @foreach($list as $item)
                            <div class="debug-item">
                                <div class="debug-label">{{ $item['label'] ?? 'Unknown' }}</div>
                                <div class="debug-value">
                                    @if(($item['type'] ?? '') === 'code_html')
                                        <pre>{!! $item['value'] ?? '' !!}</pre>
                                    @elseif(($item['type'] ?? '') === 'code')
                                        <pre><code>{{ $item['value'] ?? '' }}</code></pre>
                                    @elseif(($item['type'] ?? '') === 'debug_file')
                                        @php
                                            $editor = $item['editor'] ?? config('trace.editor', 'phpstorm');
                                            $file = $item['file'] ?? '';
                                            $line = $item['line'] ?? 1;
                                        @endphp
                                        <a href="{{ $editor }}://open?file={{ urlencode($file) }}&line={{ $line }}" 
                                           style="color: #68d391; text-decoration: none;">
                                            {{ $item['value'] ?? $file }}
                                        </a>
                                    @else
                                        {{ is_string($item['value'] ?? '') ? $item['value'] : json_encode($item['value'], JSON_UNESCAPED_UNICODE) }}
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            
            @if($isDebug && isset($exception) && is_object($exception))
                <div class="debug-section" style="margin-top: 20px;">
                    <div class="debug-header">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                        Exception Details
                    </div>
                    <div class="debug-content">
                        <div class="debug-item">
                            <div class="debug-label">Exception Class</div>
                            <div class="debug-value">{{ get_class($exception) }}</div>
                        </div>
                        <div class="debug-item">
                            <div class="debug-label">File</div>
                            <div class="debug-value">{{ $exception->getFile() }}:{{ $exception->getLine() }}</div>
                        </div>
                        <div class="debug-item">
                            <div class="debug-label">Stack Trace</div>
                            <div class="debug-value">
                                <pre>{{ $exception->getTraceAsString() }}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
            
            <div class="meta-info">
                <span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    {{ $timestamp }}
                </span>
                <span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20.24 12.24a6 6 0 0 0-8.49-8.49L5 10.5V19h8.5z"></path>
                        <line x1="16" y1="8" x2="2" y2="22"></line>
                        <line x1="17.5" y1="15" x2="9" y2="15"></line>
                    </svg>
                    ID: {{ $requestId }}
                </span>
            </div>
        </div>
    </div>
    
    <script>
        // 动态生成粒子
        (function() {
            const particlesContainer = document.getElementById('particles');
            if (!particlesContainer) return;
            
            for (let i = 0; i < 30; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 15 + 's';
                particle.style.animationDuration = (10 + Math.random() * 10) + 's';
                particlesContainer.appendChild(particle);
            }
        })();
    </script>
</body>
</html>
