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

/* ===== JSON Label/Value 通用样式（兼容动态渲染） ===== */
#trace-debug-panel .json-label {
    flex-shrink: 0;
    min-width: 120px;
    font-size: 12px;
    color: #8b949e;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

#trace-debug-panel .json-label a {
    color: #7f9cf5;
    text-decoration: none;
    font-size: 13px;
}

#trace-debug-panel .json-label a:hover {
    color: #a78bfa;
    text-decoration: underline;
}

/* Route 标签页：控制器文件 / 路由文件 标签视觉区分 */
#trace-debug-panel .json-label.label-controller-file {
    color: #7f9cf5;
    font-weight: 600;
}

#trace-debug-panel .json-label.label-route-file {
    color: #50c878;
    font-weight: 600;
}

#trace-debug-panel .json-right {
    flex-shrink: 0;
    font-size: 12px;
    color: #58a6ff;
    margin-left: auto;
    text-align: right;
}

#trace-debug-panel .json-string-content {
    flex: 1;
    font-size: 13px;
    color: #e6edf3;
    word-break: break-all;
}

/* IDE 编辑器文件链接（phpdebugbar-link） */
#trace-debug-panel .json-string-content .phpdebugbar-link {
    color: #7f9cf5;
    text-decoration: none;
    font-size: inherit;
    transition: color 0.2s ease, background 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    background: rgba(102, 126, 234, 0.08);
    border-radius: 4px;
    white-space: nowrap;
}

#trace-debug-panel .json-string-content .phpdebugbar-link:hover {
    color: #a78bfa;
    text-decoration: none;
    background: rgba(102, 126, 234, 0.18);
}

#trace-debug-panel .json-string-content .phpdebugbar-link::before {
    content: '📄';
    font-size: 11px;
    flex-shrink: 0;
}

/* ===== JSON 展开/折叠 ===== */
#trace-debug-panel .json-arrow-pre-wrapper {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    flex: 1;
}

/* 内嵌箭头（与文件路径同行时） */
#trace-debug-panel .json-arrow-pre-wrapper-inline {
    flex: 0 1 auto !important;
}

#trace-debug-panel .json-arrow {
    cursor: pointer;
    color: #8b949e;
    font-size: 12px;
    user-select: none;
    transition: transform 0.2s ease;
    flex-shrink: 0;
    line-height: 1.6;
    padding-top: 2px;
}

#trace-debug-panel .json-arrow:hover {
    color: #e2e8f0;
}

#trace-debug-panel .json-arrow.expanded {
    transform: rotate(90deg);
}

#trace-debug-panel pre.json {
    font-family: 'JetBrains Mono', 'Fira Code', Consolas, Monaco, monospace !important;
    font-size: 12px;
    line-height: 1.6;
    color: #e6edf3;
    margin: 0;
    white-space: pre-wrap;
    word-break: break-all;
    display: none;
    background: #161b22;
    padding: 12px;
    border-radius: 6px;
    border: 1px solid #30363d;
    overflow-x: auto;
}

#trace-debug-panel pre.json.show {
    display: block;
}

/* ===== SQL 分组 ===== */
#trace-debug-panel .sql-group {
    margin: 8px 0;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #30363d;
}

#trace-debug-panel .sql-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    cursor: pointer;
    user-select: none;
    transition: background 0.2s ease;
}

#trace-debug-panel .sql-group-header:hover {
    background: rgba(255, 255, 255, 0.03);
}

#trace-debug-panel .sql-group-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #e2e8f0;
}

#trace-debug-panel .sql-group-count {
    font-size: 11px;
    color: #8b949e;
    background: rgba(255, 255, 255, 0.06);
    padding: 2px 8px;
    border-radius: 10px;
}

#trace-debug-panel .sql-group-toggle {
    font-size: 10px;
    color: #8b949e;
    transition: transform 0.2s ease;
}

#trace-debug-panel .sql-group.collapsed .sql-group-toggle {
    transform: rotate(-90deg);
}

#trace-debug-panel .sql-group.collapsed .sql-group-content {
    display: none;
}

#trace-debug-panel .sql-group-content {
    padding: 0;
    list-style: none;
}

#trace-debug-panel .sql-group-content li {
    padding: 10px 16px;
    border-top: 1px solid #21262d;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
}

#trace-debug-panel .sql-group-cache .sql-group-header { background: rgba(102, 126, 234, 0.1); }
#trace-debug-panel .sql-group-session .sql-group-header { background: rgba(240, 147, 251, 0.1); }

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

/* ===== 模型操作标记 ===== */
#trace-debug-panel .model-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

#trace-debug-panel .model-badge-query {
    background: rgba(104, 211, 145, 0.15);
    color: #68d391;
}

#trace-debug-panel .model-badge-write {
    background: rgba(246, 135, 179, 0.15);
    color: #f687b3;
}

#trace-debug-panel .model-badge-mixed {
    background: rgba(250, 112, 154, 0.15);
    color: #fa709a;
}

#trace-debug-panel .model-badge-ref {
    background: rgba(160, 174, 192, 0.15);
    color: #a0aec0;
}

/* ===== 空状态提示 ===== */
#trace-debug-panel .trace-empty-state {
    text-align: center;
    padding: 40px 20px;
}

#trace-debug-panel .trace-empty-title {
    color: #a0aec0;
    font-size: 14px;
    margin-bottom: 8px;
}

#trace-debug-panel .trace-empty-tips {
    color: #718096;
    font-size: 12px;
}

/* ===== 视图文件链接 ===== */
#trace-debug-panel .view-file-link {
    display: flex;
    align-items: center;
    gap: 6px;
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
    
    // JSON 展开/折叠（使用事件委托）
    panel.addEventListener('click', function(e) {
        var arrow = e.target.closest('.json-arrow');
        if (arrow) {
            toggleJson(arrow);
        }
        
        // SQL 分组切换
        var sqlHeader = e.target.closest('.sql-group-header');
        if (sqlHeader) {
            var sqlGroup = sqlHeader.closest('.sql-group');
            if (sqlGroup) {
                sqlGroup.classList.toggle('collapsed');
            }
        }
    });
    
    // 键盘快捷键
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            // 如果 debug panel 存在且可见，按 ESC 隐藏
            if (panel.style.display !== 'none') {
                panel.style.display = 'none';
                // 显示重新打开的小按钮
                showReopenButton();
            }
        }
        
        // Ctrl+Shift+D 重新打开面板
        if (e.ctrlKey && e.shiftKey && e.key === 'D') {
            e.preventDefault();
            panel.style.display = 'block';
        }
    });
    
    // 复制 JSON 值功能
    panel.addEventListener('dblclick', function(e) {
        var jsonValue = e.target.closest('.json-string-content');
        if (jsonValue) {
            var text = jsonValue.textContent.trim();
            if (text) {
                navigator.clipboard.writeText(text).catch(function() {});
                // 短暂视觉反馈
                jsonValue.style.backgroundColor = 'rgba(102, 126, 234, 0.2)';
                setTimeout(function() {
                    jsonValue.style.backgroundColor = '';
                }, 300);
            }
        }
    });
    
    // 显示重新打开按钮（面板被 ESC 关闭后）
    function showReopenButton() {
        if (document.getElementById('trace-reopen-btn')) return;
        var btn = document.createElement('div');
        btn.id = 'trace-reopen-btn';
        btn.innerHTML = '📊';
        btn.title = '打开 Trace 调试面板 (Ctrl+Shift+D)';
        Object.assign(btn.style, {
            position: 'fixed',
            bottom: '10px',
            right: '10px',
            zIndex: '999999',
            width: '40px',
            height: '40px',
            borderRadius: '20px',
            background: 'linear-gradient(135deg, #667eea, #764ba2)',
            color: '#fff',
            fontSize: '20px',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            cursor: 'pointer',
            boxShadow: '0 4px 15px rgba(102,126,234,.3)',
            transition: 'transform .2s',
            border: 'none',
            userSelect: 'none'
        });
        btn.addEventListener('click', function() {
            panel.style.display = 'block';
            btn.remove();
        });
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
        });
        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
        document.body.appendChild(btn);
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
