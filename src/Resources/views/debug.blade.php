@extends('trace::layouts.error')

@section('title', $title ?? '系统错误/调试')

@section('content')
<style>
    .debug-container {
        width: 88%;
        min-height: calc(100vh - 50px);
        margin: 0 auto;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        padding: 30px;
    }

    .debug-title {
        text-align: center;
        margin-bottom: 30px;
        color: #ff6b6b;
        font-size: 24px;
        font-weight: 600;
    }

    .info-list {
        list-style: none;
        padding: 0;
    }

    .info-item {
        display: flex;
        padding: 15px 0;
        border-bottom: 1px dashed rgba(127, 140, 141, 0.5);
        align-items: flex-start;
    }

    .info-item:last-child {
        border-bottom: none;
    }

    .info-label {
        font-weight: bold;
        color: #4ecdc4;
        min-width: 120px;
        padding-right: 20px;
        flex-shrink: 0;
    }

    .info-value {
        flex: 1;
        word-break: break-word;
        color: #f7f7f7;
        overflow: auto;
    }

    .info-value pre {
        color: #333;
        border-radius: 4px;
        padding: 12px;
        overflow-x: auto;
        font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
        border-left: 3px solid #4ecdc4;
        margin: 5px 0;
        tab-size: 4;
        background-color: #f8f8f8;
        font-size: 13px;
    }

    .phpdebugbar-link {
        color: #4ecdc4;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .phpdebugbar-link:hover {
        color: #6ee7df;
        text-decoration: underline;
    }

    /* 响应式设计 - 移动端适配 */
    @media (max-width: 600px) {
        .debug-container {
            width: 95%;
            padding: 15px;
        }
        
        .info-item {
            flex-direction: column;
        }

        .info-label {
            margin-bottom: 8px;
            min-width: auto;
        }
    }
</style>

<div class="debug-container">
    <h1 class="debug-title">{{ $title }}</h1>

    <ul class="info-list">
        @foreach($list as $item)
            <li class="info-item">
                <span class="info-label">{{ $item['label'] }}：</span>
                <div class="info-value">
                    @if($item['type'] === 'code')
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

<footer class="error-footer" style="position: static; margin-top: 20px;">
    &copy; {{ date('Y') }} {{ config('app.name', 'Trace Debug') }} 版权所有.
    <span style="font-size: 10px; margin-left: 30px;">
        本页面由 <a href="http://www.yoc.cn/" target="_blank" style="color: #4ecdc4;">yoc.cn</a> 提供支持
    </span>
</footer>
@endsection
