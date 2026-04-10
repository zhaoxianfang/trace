{{--
    Trace 调试面板 - 仅限内部使用
    
    重要：此视图仅供 Trace 扩展包内部调用
    使用 CSS 隔离技术防止外部样式干扰
--}}
<style id="trace-panel-styles">
/* ===== CSS 重置与隔离 ===== */
#trace-debug-panel,
#trace-debug-panel *,
#trace-debug-panel *::before,
#trace-debug-panel *::after {
    all: initial;
    box-sizing: border-box !important;
    margin: 0;
    padding: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

/* ===== 面板容器 ===== */
#trace-debug-panel {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 999999 !important;
    font-size: 14px;
    line-height: 1.5;
    color: #e2e8f0;
}

/* ===== 工具栏 ===== */
#trace-debug-panel .trace-toolbar {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%) !important;
    border-top: 1px solid rgba(102, 126, 234, 0.3);
    padding: 0 20px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
}

#trace-debug-panel .trace-toolbar-left {
    display: flex;
    align-items: center;
    gap: 20px;
}

#trace-debug-panel .trace-logo {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    font-size: 15px;
    color: #667eea;
}

#trace-debug-panel .trace-logo svg {
    width: 20px;
    height: 20px;
    color: #667eea;
}

#trace-debug-panel .trace-stats {
    display: flex;
    align-items: center;
    gap: 20px;
}

#trace-debug-panel .trace-stat {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #a0aec0;
}

#trace-debug-panel .trace-stat svg {
    width: 14px;
    height: 14px;
}

#trace-debug-panel .trace-stat-value {
    color: #68d391;
    font-weight: 600;
}

#trace-debug-panel .trace-stat-value.warning {
    color: #f6e05e;
}

#trace-debug-panel .trace-stat-value.error {
    color: #fc8181;
}

/* ===== 标签页 ===== */
#trace-debug-panel .trace-tabs {
    display: flex;
    align-items: center;
    gap: 4px;
}

#trace-debug-panel .trace-tab {
    padding: 8px 16px;
    font-size: 12px;
    font-weight: 600;
    color: #a0aec0;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

#trace-debug-panel .trace-tab:hover {
    color: #e2e8f0;
    background: rgba(255, 255, 255, 0.05);
}

#trace-debug-panel .trace-tab.active {
    color: #667eea;
    border-bottom-color: #667eea;
    background: rgba(102, 126, 234, 0.1);
}

#trace-debug-panel .trace-tab-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    margin-left: 6px;
    background: rgba(102, 126, 234, 0.3);
    color: #667eea;
    border-radius: 9px;
    font-size: 10px;
    font-weight: 700;
}

/* ===== 内容区域 ===== */
#trace-debug-panel .trace-content {
    display: none;
    background: #0d1117;
    border-top: 1px solid #30363d;
    max-height: 400px;
    overflow-y: auto;
}

#trace-debug-panel .trace-content.active {
    display: block;
}

#trace-debug-panel .trace-content-inner {
    padding: 20px;
}

/* ===== 数据列表 ===== */
#trace-debug-panel .trace-list {
    list-style: none;
}

#trace-debug-panel .trace-list-item {
    padding: 12px 0;
    border-bottom: 1px solid #21262d;
    display: flex;
    align-items: flex-start;
    gap: 15px;
}

#trace-debug-panel .trace-list-item:last-child {
    border-bottom: none;
}

#trace-debug-panel .trace-label {
    flex-shrink: 0;
    width: 120px;
    font-size: 12px;
    color: #8b949e;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

#trace-debug-panel .trace-value {
    flex: 1;
    font-size: 13px;
    color: #e6edf3;
    word-break: break-all;
}

/* ===== 代码块 ===== */
#trace-debug-panel .trace-code-block {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 8px;
    padding: 16px;
    margin-top: 8px;
    overflow-x: auto;
}

#trace-debug-panel .trace-code-block pre {
    font-family: 'JetBrains Mono', 'Fira Code', Consolas, Monaco, monospace !important;
    font-size: 12px;
    line-height: 1.6;
    color: #e6edf3;
    margin: 0;
    white-space: pre-wrap;
    word-break: break-all;
}

#trace-debug-panel .trace-code-block code {
    font-family: inherit;
    color: #e6edf3;
}

/* 错误行高亮 */
#trace-debug-panel .error-line-code {
    background-color: rgba(248, 81, 73, 0.15);
    color: #f85149;
    display: block;
    border-left: 3px solid #f85149;
    padding-left: 13px;
    margin-left: -16px;
    font-weight: 600;
}

/* ===== JSON 显示 ===== */
#trace-debug-panel .trace-json {
    font-family: 'JetBrains Mono', 'Fira Code', Consolas, Monaco, monospace !important;
    font-size: 12px;
    line-height: 1.6;
    color: #e6edf3;
}

#trace-debug-panel .trace-json-key {
    color: #7ee787;
}

#trace-debug-panel .trace-json-string {
    color: #a5d6ff;
}

#trace-debug-panel .trace-json-number {
    color: #79c0ff;
}

#trace-debug-panel .trace-json-boolean {
    color: #ff7b72;
}

/* ===== SQL 显示 ===== */
#trace-debug-panel .trace-sql {
    font-family: 'JetBrains Mono', 'Fira Code', Consolas, Monaco, monospace !important;
    font-size: 12px;
    line-height: 1.6;
    color: #e6edf3;
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 8px;
    padding: 16px;
    margin-top: 8px;
    overflow-x: auto;
}

#trace-debug-panel .trace-sql-keyword {
    color: #ff7b72;
    font-weight: 600;
}

#trace-debug-panel .trace-sql-string {
    color: #a5d6ff;
}

#trace-debug-panel .trace-sql-number {
    color: #79c0ff;
}

/* ===== 折叠按钮 ===== */
#trace-debug-panel .trace-toggle {
    background: rgba(102, 126, 234, 0.2);
    border: 1px solid rgba(102, 126, 234, 0.3);
    color: #667eea;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

#trace-debug-panel .trace-toggle:hover {
    background: rgba(102, 126, 234, 0.3);
}

#trace-debug-panel .trace-toggle svg {
    width: 14px;
    height: 14px;
    transition: transform 0.2s ease;
}

#trace-debug-panel .trace-toggle.collapsed svg {
    transform: rotate(-90deg);
}

/* ===== 响应式 ===== */
@media (max-width: 768px) {
    #trace-debug-panel .trace-toolbar {
        flex-wrap: wrap;
        height: auto;
        padding: 10px;
    }
    
    #trace-debug-panel .trace-tabs {
        order: 3;
        width: 100%;
        margin-top: 10px;
        overflow-x: auto;
    }
    
    #trace-debug-panel .trace-tab {
        white-space: nowrap;
    }
}
</style>

<div id="trace-debug-panel" style="display: none;">
    <!-- 工具栏 -->
    <div class="trace-toolbar">
        <div class="trace-toolbar-left">
            <div class="trace-logo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
                TRACE
            </div>
            
            <div class="trace-stats">
                <div class="trace-stat">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <span class="trace-stat-value" id="trace-time">0ms</span>
                </div>
                <div class="trace-stat">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                    </svg>
                    <span class="trace-stat-value" id="trace-memory">0MB</span>
                </div>
            </div>
        </div>
        
        <div class="trace-tabs">
            @foreach($tabs ?? [] as $key => $label)
                <button class="trace-tab {{ $loop->first ? 'active' : '' }}" data-tab="{{ $key }}">
                    {{ $label }}
                    @if(isset($badges[$key]) && $badges[$key] > 0)
                        <span class="trace-tab-badge">{{ $badges[$key] }}</span>
                    @endif
                </button>
            @endforeach
        </div>
        
        <button class="trace-toggle" id="trace-panel-toggle" title="Toggle Panel">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </button>
    </div>
    
    <!-- 内容区域 -->
    @foreach($contents ?? [] as $key => $content)
        <div class="trace-content {{ $loop->first ? 'active' : '' }}" data-content="{{ $key }}">
            <div class="trace-content-inner">
                {!! $content !!}
            </div>
        </div>
    @endforeach
</div>

<script>
(function() {
    'use strict';
    
    // 确保只初始化一次
    if (window.tracePanelInitialized) return;
    window.tracePanelInitialized = true;
    
    const panel = document.getElementById('trace-debug-panel');
    if (!panel) return;
    
    // 显示面板
    panel.style.display = 'block';
    
    // 标签切换
    const tabs = panel.querySelectorAll('.trace-tab');
    const contents = panel.querySelectorAll('.trace-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const targetTab = this.dataset.tab;
            
            // 更新标签状态
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // 更新内容显示
            contents.forEach(c => {
                c.classList.toggle('active', c.dataset.content === targetTab);
            });
        });
    });
    
    // 折叠/展开
    const toggle = document.getElementById('trace-panel-toggle');
    let isExpanded = true;
    
    if (toggle) {
        toggle.addEventListener('click', function() {
            isExpanded = !isExpanded;
            this.classList.toggle('collapsed', !isExpanded);
            
            contents.forEach(c => {
                c.style.display = isExpanded ? 'block' : 'none';
            });
        });
    }
    
    // 更新性能数据
    @if(isset($performance))
        const timeEl = document.getElementById('trace-time');
        const memoryEl = document.getElementById('trace-memory');
        
        @if(isset($performance['time']))
            if (timeEl) timeEl.textContent = '{{ $performance['time'] }}';
        @endif
        
        @if(isset($performance['memory']))
            if (memoryEl) memoryEl.textContent = '{{ $performance['memory'] }}';
        @endif
    @endif
})();
</script>
