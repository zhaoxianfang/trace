<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Debug' }} - Trace Debugger</title>
    <style>
        /* ===== 基础重置 ===== */
        .trace-debug-page *, .trace-debug-page *::before, .trace-debug-page *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        /* ===== 页面容器 ===== */
        .trace-debug-page {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
            padding: 40px 20px;
            position: relative;
            overflow-x: hidden;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
        }
        
        /* ===== 动态网格背景 ===== */
        .trace-debug-page .grid-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(102, 126, 234, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(102, 126, 234, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: grid-move 20s linear infinite;
            pointer-events: none;
            z-index: 0;
        }
        
        @keyframes grid-move {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        /* ===== 浮动光晕 ===== */
        .trace-debug-page .glow {
            position: fixed;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.15;
            pointer-events: none;
            z-index: 0;
        }
        
        .trace-debug-page .glow-1 {
            background: #667eea;
            top: -200px;
            right: -200px;
            animation: float-glow 15s ease-in-out infinite;
        }
        
        .trace-debug-page .glow-2 {
            background: #f093fb;
            bottom: -200px;
            left: -200px;
            animation: float-glow 15s ease-in-out infinite reverse;
        }
        
        @keyframes float-glow {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, 30px) scale(1.1); }
        }
        
        /* ===== 主容器 ===== */
        .trace-debug-page .container {
            position: relative;
            z-index: 10;
            max-width: 900px;
            margin: 0 auto;
        }
        
        /* ===== 头部 ===== */
        .trace-debug-page .header {
            text-align: center;
            margin-bottom: 40px;
            animation: fade-in 0.6s ease-out;
        }
        
        .trace-debug-page .header-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.4);
            animation: pulse-icon 2s ease-in-out infinite;
        }
        
        @keyframes pulse-icon {
            0%, 100% { transform: scale(1); box-shadow: 0 10px 40px rgba(102, 126, 234, 0.4); }
            50% { transform: scale(1.05); box-shadow: 0 15px 50px rgba(102, 126, 234, 0.6); }
        }
        
        .trace-debug-page .header-icon svg {
            width: 40px;
            height: 40px;
            color: white;
        }
        
        .trace-debug-page h1 {
            font-size: 32px;
            color: #fff;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .trace-debug-page .subtitle {
            color: #a0aec0;
            font-size: 16px;
        }
        
        /* ===== 调试卡片 ===== */
        .trace-debug-page .debug-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
            animation: slide-up 0.6s ease-out 0.1s both;
        }
        
        @keyframes slide-up {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .trace-debug-page .card-header {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .trace-debug-page .card-title {
            font-size: 18px;
            color: #fff;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* ===== 调试列表 ===== */
        .trace-debug-page .debug-list {
            padding: 10px;
        }
        
        .trace-debug-page .debug-item {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 10px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
            animation: fade-in 0.5s ease-out both;
        }
        
        .trace-debug-page .debug-item:hover {
            background: rgba(0, 0, 0, 0.3);
            border-color: rgba(102, 126, 234, 0.3);
            transform: translateX(5px);
        }
        
        .trace-debug-page .debug-item:nth-child(1) { animation-delay: 0.1s; }
        .trace-debug-page .debug-item:nth-child(2) { animation-delay: 0.15s; }
        .trace-debug-page .debug-item:nth-child(3) { animation-delay: 0.2s; }
        .trace-debug-page .debug-item:nth-child(4) { animation-delay: 0.25s; }
        .trace-debug-page .debug-item:nth-child(5) { animation-delay: 0.3s; }
        
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .trace-debug-page .debug-item {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 10px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
            animation: fade-in 0.5s ease-out both;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .trace-debug-page .debug-item:hover {
            background: rgba(0, 0, 0, 0.3);
            border-color: rgba(102, 126, 234, 0.3);
            transform: translateX(5px);
        }
        
        .trace-debug-page .item-header {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            min-width: 140px;
        }
        
        .trace-debug-page .item-type {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .trace-debug-page .item-type.string {
            background: rgba(104, 211, 145, 0.2);
            color: #68d391;
        }
        
        .trace-debug-page .item-type.code {
            background: rgba(246, 135, 179, 0.2);
            color: #f687b3;
        }
        
        .trace-debug-page .item-type.debug_file {
            background: rgba(102, 126, 234, 0.2);
            color: #667eea;
        }
        
        .trace-debug-page .item-type.code_html {
            background: rgba(250, 112, 154, 0.2);
            color: #fa709a;
        }
        
        .trace-debug-page .item-label {
            color: #fff;
            font-weight: 600;
            font-size: 14px;
            white-space: nowrap;
        }
        
        .trace-debug-page .item-value {
            color: #cbd5e0;
            font-size: 13px;
            line-height: 1.6;
            word-break: break-all;
            flex: 1;
        }
        
        .trace-debug-page .item-value-inline {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* ===== 代码块 ===== */
        .trace-debug-page .code-block {
            background: #0d1117;
            border-radius: 10px;
            padding: 16px;
            margin-top: 12px;
            overflow-x: auto;
            border: 1px solid #30363d;
        }
        
        .trace-debug-page .code-block pre {
            font-family: 'JetBrains Mono', 'Fira Code', Consolas, Monaco, monospace;
            font-size: 12px;
            line-height: 1.6;
            color: #e6edf3;
            margin: 0;
        }
        
        .trace-debug-page .code-block code {
            color: #e6edf3;
        }
        
        /* 错误行高亮 */
        .trace-debug-page .error-line-code {
            background-color: rgba(248, 81, 73, 0.15);
            color: #f85149;
            display: block;
            border-left: 3px solid #f85149;
            padding-left: 13px;
            margin-left: -16px;
            font-weight: 600;
        }
        
        /* ===== 文件链接 ===== */
        .trace-debug-page .file-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #667eea;
            text-decoration: none;
            padding: 8px 14px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 8px;
            font-size: 13px;
            transition: all 0.3s ease;
            margin-top: 8px;
        }
        
        .trace-debug-page .file-link:hover {
            background: rgba(102, 126, 234, 0.2);
            color: #764ba2;
            transform: translateY(-1px);
        }
        
        /* ===== 操作按钮 ===== */
        .trace-debug-page .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 30px;
            animation: fade-in 0.6s ease-out 0.4s both;
        }
        
        .trace-debug-page .btn {
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .trace-debug-page .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .trace-debug-page .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
        }
        
        .trace-debug-page .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #e2e8f0;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .trace-debug-page .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        
        /* ===== 元信息 ===== */
        .trace-debug-page .meta-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 25px;
            background: rgba(0, 0, 0, 0.2);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 12px;
            color: #718096;
        }
        
        .trace-debug-page .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* ===== 响应式 ===== */
        @media (max-width: 640px) {
            .trace-debug-page {
                padding: 20px 15px;
            }
            
            .trace-debug-page h1 {
                font-size: 24px;
            }
            
            .trace-debug-page .debug-item {
                padding: 15px;
            }
            
            .trace-debug-page .actions {
                flex-direction: column;
            }
            
            .trace-debug-page .btn {
                width: 100%;
                justify-content: center;
            }
            
            .trace-debug-page .meta-bar {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="trace-debug-page">
        <!-- 背景特效 -->
        <div class="grid-bg"></div>
        <div class="glow glow-1"></div>
        <div class="glow glow-2"></div>
        
        <!-- 主容器 -->
        <div class="container">
            <!-- 头部 -->
            <div class="header">
                <div class="header-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                </div>
                <h1>{{ $title ?? 'Debug Information' }}</h1>
                <p class="subtitle">Trace Debugger</p>
            </div>
            
            <!-- 调试卡片 -->
            <div class="debug-card">
                <div class="card-header">
                    <div class="card-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        Debug Details
                    </div>
                </div>
                
                <div class="debug-list">
                    @forelse($list ?? [] as $index => $item)
                        @php
                            $type = $item['type'] ?? 'string';
                            $label = $item['label'] ?? 'Unknown';
                            $value = $item['value'] ?? '';
                        @endphp
                        <div class="debug-item">
                            <div class="item-header">
                                <span class="item-type {{ $type }}">{{ $type }}</span>
                                <span class="item-label">{{ $label }}</span>
                            </div>
                            <div class="item-value">
                                @if($type === 'code_html')
                                    <div class="code-block">
                                        <pre>{!! $value !!}</pre>
                                    </div>
                                @elseif($type === 'code')
                                    <div class="code-block">
                                        <pre><code>{{ $value }}</code></pre>
                                    </div>
                                @elseif($type === 'debug_file')
                                    @php
                                        $editor = $item['editor'] ?? config('trace.editor', 'phpstorm');
                                        $file = $item['file'] ?? '';
                                        $line = $item['line'] ?? 1;
                                    @endphp
                                    <a href="{{ $editor }}://open?file={{ urlencode($file) }}&line={{ $line }}" class="file-link">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                            <polyline points="14 2 14 8 20 8"></polyline>
                                        </svg>
                                        {{ $value }}
                                    </a>
                                @else
                                    <span class="item-value-inline">{{ is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="debug-item" style="flex-direction: column; text-align: center; padding: 40px 20px;">
                            <div style="color: #a0aec0; font-size: 15px; margin-bottom: 10px;">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 15px; opacity: 0.5;">
                                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                    <line x1="8" y1="21" x2="16" y2="21"></line>
                                    <line x1="12" y1="17" x2="12" y2="21"></line>
                                </svg>
                                <div>暂无调试内容</div>
                            </div>
                            <div style="color: #718096; font-size: 12px;">
                                可使用 <code style="background: rgba(102, 126, 234, 0.2); color: #667eea; padding: 2px 6px; border-radius: 4px; font-family: 'JetBrains Mono', monospace;">trace(mixed ...$args)</code> 函数进行调试
                            </div>
                        </div>
                    @endforelse
                </div>
                
                <div class="meta-bar">
                    <div class="meta-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        {{ date('Y-m-d H:i:s') }}
                    </div>
                    <div class="meta-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.24 12.24a6 6 0 0 0-8.49-8.49L5 10.5V19h8.5z"></path>
                        </svg>
                        Trace Debugger
                    </div>
                </div>
            </div>
            
            <!-- 操作按钮 -->
            <div class="actions">
                <a href="{{ url('/') }}" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    </svg>
                    Home
                </a>
                <button onclick="history.back()" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"></path>
                    </svg>
                    Back
                </button>
            </div>
        </div>
    </div>
</body>
</html>
