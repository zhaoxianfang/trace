{{--
    Trace 调试面板视图 - 美化版
    用于渲染调试工具栏和内容面板
--}}
<style>
    /* 异常代码块样式 */
    .exception-code-block {
        background: #0d1117 !important;
        color: #c9d1d9 !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        border-radius: 12px !important;
        padding: 16px !important;
        font-family: 'JetBrains Mono', 'Fira Code', Consolas, Monaco, monospace !important;
        font-size: 12px !important;
        line-height: 1.6 !important;
        overflow-x: auto !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05) !important;
    }
    
    .exception-code-block .error-line-code {
        background: linear-gradient(90deg, rgba(255, 107, 107, 0.2) 0%, transparent 100%) !important;
        color: #ff7b7b !important;
        display: block !important;
        font-weight: 600 !important;
        border-left: 3px solid #ff6b6b !important;
        margin: 0 -16px !important;
        padding: 4px 16px !important;
        text-shadow: 0 0 10px rgba(255, 107, 107, 0.5) !important;
    }

    /* Trace 面板容器 - 毛玻璃效果 */
    #trace-tools-box {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 999999;
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    }

    /* Logo 区域 */
    .trace-logo {
        position: fixed;
        bottom: 10px;
        right: 10px;
        background: rgba(15, 23, 42, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 12px;
        padding: 10px 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 
            0 4px 20px rgba(0, 0, 0, 0.4),
            0 0 0 1px rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
        z-index: 1000000;
    }

    .trace-logo:hover {
        transform: translateY(-2px);
        box-shadow: 
            0 8px 30px rgba(0, 0, 0, 0.5),
            0 0 0 1px rgba(78, 205, 196, 0.3);
    }

    .trace-logo .logo {
        height: 20px;
        filter: drop-shadow(0 0 5px rgba(78, 205, 196, 0.5));
    }

    .trace-logo .title {
        font-weight: 700;
        font-size: 14px;
        background: linear-gradient(135deg, #4ecdc4 0%, #6ee7df 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    /* 标签容器 */
    .tabs-container {
        background: rgba(15, 23, 42, 0.98);
        backdrop-filter: blur(20px);
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 -4px 30px rgba(0, 0, 0, 0.4);
        max-height: 400px;
        display: none;
        flex-direction: column;
    }

    .tabs-container.active {
        display: flex;
    }

    /* 标签头部 */
    .tabs-header {
        display: flex;
        align-items: center;
        padding: 0 20px;
        background: rgba(0, 0, 0, 0.3);
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        overflow-x: auto;
        scrollbar-width: none;
    }

    .tabs-header::-webkit-scrollbar {
        display: none;
    }

    .tabs-logo-small {
        height: 18px;
        margin-right: 15px;
        opacity: 0.8;
    }

    .tabs-menu {
        display: flex;
        gap: 5px;
        flex: 1;
    }

    .tabs-item {
        padding: 14px 20px;
        color: rgba(255, 255, 255, 0.5);
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        white-space: nowrap;
        position: relative;
        transition: all 0.3s ease;
        border-bottom: 2px solid transparent;
    }

    .tabs-item:hover {
        color: rgba(255, 255, 255, 0.8);
        background: rgba(255, 255, 255, 0.03);
    }

    .tabs-item.active {
        color: #4ecdc4;
        border-bottom-color: #4ecdc4;
    }

    .tabs-item.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 50%;
        transform: translateX(-50%);
        width: 20px;
        height: 2px;
        background: #4ecdc4;
        box-shadow: 0 0 10px #4ecdc4;
    }

    /* 关闭按钮 */
    .tabs-close {
        padding: 14px 20px;
        color: rgba(255, 255, 255, 0.5);
        font-size: 24px;
        cursor: pointer;
        transition: all 0.3s ease;
        line-height: 1;
    }

    .tabs-close:hover {
        color: #ff6b6b;
        transform: scale(1.1);
    }

    /* 标签内容 */
    .tabs-content {
        display: none;
        padding: 20px;
        overflow-y: auto;
        max-height: 320px;
        background: rgba(0, 0, 0, 0.2);
    }

    .tabs-content.active {
        display: block;
    }

    .tabs-content ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .tabs-content li {
        padding: 12px 16px;
        margin-bottom: 8px;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.05);
        display: flex;
        align-items: flex-start;
        gap: 15px;
        transition: all 0.2s ease;
    }

    .tabs-content li:hover {
        background: rgba(255, 255, 255, 0.05);
        border-color: rgba(78, 205, 196, 0.2);
        transform: translateX(3px);
    }

    /* JSON 标签 */
    .json-label {
        color: #4ecdc4;
        font-weight: 600;
        font-size: 12px;
        min-width: 120px;
        flex-shrink: 0;
    }

    .json-string-content {
        color: rgba(255, 255, 255, 0.7);
        font-size: 12px;
        flex: 1;
        word-break: break-word;
    }

    .json-right {
        color: rgba(255, 255, 255, 0.4);
        font-size: 11px;
        margin-left: auto;
        padding-left: 15px;
    }

    /* SQL 分组 */
    .sql-group {
        width: 100%;
        margin-bottom: 12px;
        background: rgba(255, 255, 255, 0.02);
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.05);
        overflow: hidden;
    }

    .sql-group-header {
        padding: 14px 18px;
        background: rgba(78, 205, 196, 0.1);
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s ease;
    }

    .sql-group-header:hover {
        background: rgba(78, 205, 196, 0.15);
    }

    .sql-group-title {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #4ecdc4;
        font-weight: 600;
        font-size: 13px;
    }

    .sql-group-count {
        background: rgba(78, 205, 196, 0.2);
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
    }

    .sql-group-toggle {
        transition: transform 0.3s ease;
        color: rgba(255, 255, 255, 0.5);
    }

    .sql-group.collapsed .sql-group-toggle {
        transform: rotate(-90deg);
    }

    .sql-group-content {
        padding: 10px;
        display: block;
    }

    .sql-group.collapsed .sql-group-content {
        display: none;
    }

    /* 链接样式 */
    a.json-label {
        text-decoration: none;
        color: #4ecdc4;
        border-bottom: 1px dashed rgba(78, 205, 196, 0.3);
        transition: all 0.3s ease;
    }

    a.json-label:hover {
        color: #6ee7df;
        border-bottom-color: #6ee7df;
    }

    /* 滚动条美化 */
    .tabs-content::-webkit-scrollbar {
        width: 8px;
    }

    .tabs-content::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.2);
    }

    .tabs-content::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 4px;
    }

    .tabs-content::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    /* 响应式 */
    @media (max-width: 768px) {
        .tabs-item {
            padding: 12px 15px;
            font-size: 12px;
        }
        
        .tabs-content li {
            flex-direction: column;
            gap: 8px;
        }
        
        .json-label {
            min-width: auto;
        }
        
        .json-right {
            margin-left: 0;
            padding-left: 0;
        }
    }
</style>

<div id="trace-tools-box">
    <div class="trace-logo" onclick="document.querySelector('.tabs-container').classList.toggle('active');">
        <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAYAAACNiR0NAAAAAXNSR0IArs4c6QAAAcBJREFUOE/F1MtKAlEYB/DvaI1OlJIuhBYKFbSzi9lKcIJadnkIoQeoTYvw0mu0laBlPYAOQUG2cFGEQdioQXbTUcsy9cQZm+HkXBRcdEAGjt/8zn/OzHcQ9DEwH4qQMhQ8kK5GAxn9iRMhDsw4DBi4Th2K9kI1QTXUvaw+rAI7j4fDvR5NL7ECSqlMOKEJ2WcAxIz2GgiSgBEvb4UE5g8DHGsdizgdE8Huu7B3B4CAZOROAAnHf0rE50pauCnA7N75vLTLMohwJ52VtfEExp51APeadqpfOHuV40vFshTCF0tJlgokkzb/dnF0atOlt49NsdDIHu3mag+303KNIch6t8BsnwTLECNaXIt2+aZW6b7+ep2sm0fGHU9ncfh8EZQ1+wLlaqb9VawIwjCB5LmBwGb1sY4/zCy9Bf8LMp5VYNwrSqCBExKJvBQCkysNiplTIL/uYfhS6GICmpxLb9W7rEMLkr49DMmF/dSy8h3KQD4eiCCk7uNGrZ0uFZpzOr0X9cUulGNNdTiQNoQ2cDSsBdKp6IV0z0M6LQ0SqGXCUX/0MqmV2PCAlfo8Hoh8v7c2yvlm2QiS8Z6gXj/rzf8AmFQQJJO/2LAAAAAASUVORK5CYII=" alt="Logo" class="logo">
        <span class="title">Trace</span>
    </div>
    
    <div class="tabs-container">
        <div class="tabs-header">
            <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAYAAACNiR0NAAAAAXNSR0IArs4c6QAAAcBJREFUOE/F1MtKAlEYB/DvaI1OlJIuhBYKFbSzi9lKcIJadnkIoQeoTYvw0mu0laBlPYAOQUG2cFGEQdioQXbTUcsy9cQZm+HkXBRcdEAGjt/8zn/OzHcQ9DEwH4qQMhQ8kK5GAxn9iRMhDsw4DBi4Th2K9kI1QTXUvaw+rAI7j4fDvR5NL7ECSqlMOKEJ2WcAxIz2GgiSgBEvb4UE5g8DHGsdizgdE8Huu7B3B4CAZOROAAnHf0rE50pauCnA7N75vLTLMohwJ52VtfEExp51APeadqpfOHuV40vFshTCF0tJlgokkzb/dnF0atOlt49NsdDIHu3mag+303KNIch6t8BsnwTLECNaXIt2+aZW6b7+ep2sm0fGHU9ncfh8EZQ1+wLlaqb9VawIwjCB5LmBwGb1sY4/zCy9Bf8LMp5VYNwrSqCBExKJvBQCkysNiplTIL/uYfhS6GICmpxLb9W7rEMLkr49DMmF/dSy8h3KQD4eiCCk7uNGrZ0uFZpzOr0X9cUulGNNdTiQNoQ2cDSsBdKp6IV0z0M6LQ0SqGXCUX/0MqmV2PCAlfo8Hoh8v7c2yvlm2QiS8Z6gXj/rzf8AmFQQJJO/2LAAAAAASUVORK5CYII=" alt="Logo" class="tabs-logo-small">
            <div class="tabs-menu">
                @foreach(array_keys($trace) as $key => $name)
                    @php
                        $tabKey = $key + 1;
                        $activeClass = $key < 1 ? 'active' : '';
                        $isSelected = $key < 1 ? 'true' : 'false';
                    @endphp
                    <div class='tabs-item {{ $activeClass }}' data-tab='tab{{ $tabKey }}' tabindex='0' role='tab' aria-selected='{{ $isSelected }}'>
                        {{ $name }}
                    </div>
                @endforeach
            </div>
            <div class="tabs-close" title="关闭调试面板 (ESC)" onclick="document.querySelector('.tabs-container').classList.remove('active');">×</div>
        </div>

        @php $tabIndex = 0; @endphp
        @foreach($trace as $key => $tabs)
            @php
                $tabKey = ++$tabIndex;
                $active = $tabIndex < 2 ? 'active' : '';
            @endphp
            <div id="tab{{ $tabKey }}" class="tabs-content {{ $active }}" role="tabpanel" aria-labelledby="tab{{ $tabKey }}">
                <ul>
                    @foreach($tabs as $k => $item)
                        @if(is_array($item) && isset($item['type']) && $item['type'] == 'sql_group')
                            {{-- SQL 分组通过组件渲染 --}}
                            @include('trace::components.sql-group', [
                                'name' => $item['name'] ?? 'SQL Group',
                                'class' => $item['class'] ?? 'sql-group',
                                'collapsed' => $item['collapsed'] ?? false,
                                'count' => $item['count'] ?? 0,
                                'sqls' => $item['sqls'] ?? []
                            ])
                        @elseif(is_array($item) && isset($item['has_html']) && $item['has_html'])
                            {{-- 带有 HTML 提示的空状态 --}}
                            <li>
                                <span class='json-label'>{{ $item['message'] }}</span>
                                <span class='json-string-content' style="font-size: 12px; color: rgba(255,255,255,0.4);">
                                    提示: {{ $item['tips'] }}
                                </span>
                            </li>
                        @else
                            <li>
                                @if(is_array($item) && !empty($item['type']) && $item['type'] == 'trace')
                                    {{-- Trace 数据项 --}}
                                    @include('trace::components.trace-item', ['data' => $item])
                                @elseif(is_array($item) && isset($item['raw_html']) && $item['raw_html'])
                                    {{-- 带有原始 HTML 的内容（如异常文件链接） --}}
                                    @if(is_string($item['content']) && str_contains($item['content'], 'error-line-code'))
                                        {{-- 异常代码展示 --}}
                                        <pre class="exception-code-block">{!! $item['content'] !!}</pre>
                                    @else
                                        {!! $item['content'] !!}
                                    @endif
                                @else
                                    {{-- 左侧 label --}}
                                    @if(is_array($item) && !empty($item['label']))
                                        <span class='json-label'>{{ $item['label'] }}</span>
                                    @elseif(is_string($k))
                                        <span class='json-label'>{{ $k }}</span>
                                    @endif

                                    {{-- 中间内容 --}}
                                    @if(!is_array($item))
                                        @php $class = is_numeric($k) ? 'json-label' : 'json-string-content'; @endphp
                                        @if(is_scalar($item) || is_null($item))
                                            <div class='{{ $class }}'>{{ format_param($item) }}</div>
                                        @else
                                            <div class='{{ $class }}'>{{ ucfirst(gettype($item)) }}:{{ get_class($item) }}</div>
                                        @endif
                                    @else
                                        @if(!empty(array_diff(array_keys($item), ['label', 'right', 'raw_html', 'content'])))
                                            @include('trace::components.json-display', [
                                                'json' => json_encode($item, JSON_UNESCAPED_UNICODE)
                                            ])
                                        @elseif(empty($item))
                                            <span class='json-string-content'>[]</span>
                                        @endif
                                    @endif

                                    {{-- 右侧 right --}}
                                    @if(is_array($item) && !empty($item['right']))
                                        <span class='json-right'>{{ $item['right'] }}</span>
                                    @endif
                                @endif
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</div>

<script>
    // 标签切换功能
    document.querySelectorAll('.tabs-item').forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // 移除所有活动状态
            document.querySelectorAll('.tabs-item').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tabs-content').forEach(c => c.classList.remove('active'));
            
            // 添加活动状态
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });

    // ESC 键关闭面板
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelector('.tabs-container').classList.remove('active');
        }
    });
</script>
