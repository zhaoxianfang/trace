// ========================================
// Trace 调试工具 JavaScript
// ========================================

// 防止重复初始化
if (!window.__traceInitialized) {
    window.__traceInitialized = true;

    const CONFIG = {
        clickDebounceTime: 300,
        textMaxLength: 100,
    };

    let isClickAllowed = true;

    function trace_reset_allowed_value() {
        isClickAllowed = false;
        setTimeout(function() { isClickAllowed = true; }, CONFIG.clickDebounceTime);
    }

    /** 安全获取元素 */
    function $t(selector, parent) {
        parent = parent || document;
        try { return parent.querySelector(selector); } catch (e) { return null; }
    }

    /** 安全获取元素列表 */
    function $$t(selector, parent) {
        parent = parent || document;
        try { return Array.from(parent.querySelectorAll(selector)); } catch (e) { return []; }
    }

    function initTrace() {
        initTabSwitch();
        initJsonDisplay();
        initTextExpansion();
        initClickHandlers();
        initKeyboardShortcuts();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTrace);
    } else {
        initTrace();
    }

    /** Tab 切换 */
    function initTabSwitch() {
        var tabItems = $$t("#trace-tools-box .tabs-item");
        var tabContents = $$t("#trace-tools-box .tabs-content");
        if (!tabItems.length || !tabContents.length) return;

        function activateTab(index) {
            tabItems.forEach(function(item, i) {
                if (item && tabContents[i]) {
                    item.classList.toggle("active", i === index);
                    tabContents[i].classList.toggle("active", i === index);
                }
            });
        }

        tabItems.forEach(function(tab, index) {
            if (tab) {
                tab.addEventListener("click", function(e) {
                    e.stopPropagation();
                    activateTab(index);
                });
            }
        });

        activateTab(0);
    }

    /** JSON 显示初始化 */
    function initJsonDisplay() {
        var jsonElements = $$t("#trace-tools-box .json");
        var arrowElements = $$t("#trace-tools-box .json-arrow");

        if (!jsonElements.length) return;

        jsonElements.forEach(function(jsonElement, index) {
            if (!jsonElement) return;
            try {
                var jsonText = extractJson(jsonElement.textContent ? jsonElement.textContent.trim() : '');
                jsonElement.setAttribute("data-original", jsonText || '[]');

                if (arrowElements[index] && jsonText) {
                    var jsonData;
                    try { jsonData = JSON.parse(jsonText); } catch (e) { jsonData = { error: 'Invalid JSON' }; }
                    var len = Array.isArray(jsonData) ? jsonData.length : Object.keys(jsonData).length;
                    var prefix = Array.isArray(jsonData) ? 'array' : 'object';
                    jsonElement.textContent = JSON.stringify(jsonData, null, 4);
                    arrowElements[index].textContent = '\u25B6 ' + prefix + ':' + len;
                }
            } catch (e) { /* ignore */ }
        });
    }

    /** 文本展开/收起 */
    function initTextExpansion() {
        $$t('#trace-tools-box .json-label').forEach(function(label) {
            if (!label || label.closest('.sql-group-content') || label.querySelector('.expand-btn')) return;

            var text = label.textContent;
            if (!text || text.length <= CONFIG.textMaxLength) return;

            var full = text;
            var short = text.substring(0, CONFIG.textMaxLength) + '...';

            var wrapper = document.createElement('span');
            wrapper.className = 'expand-btn-wrapper';
            var btn = document.createElement('span');
            btn.className = 'expand-btn';
            btn.textContent = '\u5c55\u5f00';
            wrapper.appendChild(btn);

            label.textContent = short;
            label.appendChild(wrapper);
            label.classList.add('truncated');

            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
                var expanded = label.classList.toggle('expanded');
                btn.textContent = expanded ? '\u6536\u8d77' : '\u5c55\u5f00';
                label.textContent = expanded ? full : short;
                label.appendChild(wrapper);
                expanded ? label.classList.remove('truncated') : label.classList.add('truncated');
            });
        });

        initSqlGroups();
    }

    /** SQL分组展开/收起 */
    function initSqlGroups() {
        $$t('#trace-tools-box .sql-group-header').forEach(function(header) {
            if (!header) return;
            header.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
                var group = header.parentElement;
                if (group && group.classList.contains('sql-group')) {
                    group.classList.toggle('collapsed');
                }
            });
        });
    }

    /** 点击事件 */
    function initClickHandlers() {
        document.addEventListener('click', function(e) {
            var tabsLogoEvent = e.target.closest("#trace-tools-box .trace-logo");
            var closeButton = e.target.closest("#trace-tools-box .tabs-close");
            var tabsContainerDom = $t("#trace-tools-box .tabs-container");
            var tabsLogoDom = $t("#trace-tools-box .trace-logo");

            if (!tabsContainerDom || !tabsLogoDom) return;

            if (tabsLogoEvent && isClickAllowed) {
                tabsLogoDom.classList.add('hidden');
                tabsContainerDom.classList.add('visible');
                trace_reset_allowed_value();
            }

            if (closeButton && isClickAllowed) {
                tabsContainerDom.classList.remove('visible');
                tabsLogoDom.classList.remove('hidden');
                trace_reset_allowed_value();
            }
        });
    }

    /** 键盘快捷键 */
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') return;
            var tabsContainerDom = $t("#trace-tools-box .tabs-container");
            var tabsLogoDom = $t("#trace-tools-box .trace-logo");
            if (tabsContainerDom && tabsLogoDom && tabsContainerDom.classList.contains('visible')) {
                tabsContainerDom.classList.remove('visible');
                tabsLogoDom.classList.remove('hidden');
            }
        });
    }

    /** 提取最外层的 {} 或 [] */
    function extractJson(text) {
        if (!text || typeof text !== 'string') return null;
        text = text.trim();
        if (!text.length) return null;

        var stack = [], start = -1;
        for (var i = 0; i < text.length; i++) {
            var ch = text[i];
            if (ch === '{' || ch === '[') {
                if (!stack.length) start = i;
                stack.push(ch);
            } else if (ch === '}' || ch === ']') {
                if (!stack.length) continue;
                var open = stack.pop();
                if ((open === '{' && ch !== '}') || (open === '[' && ch !== ']')) return null;
                if (!stack.length) return text.slice(start, i + 1);
            }
        }
        return null;
    }

    /** 展开/收起 JSON */
    function toggleJson(arrowElement) {
        if (!arrowElement) return;
        var preElement = arrowElement.nextElementSibling;
        if (!preElement || !preElement.getAttribute) return;

        var originalJson = preElement.getAttribute("data-original");
        if (!originalJson) return;

        var jsonData;
        try { jsonData = JSON.parse(originalJson); } catch (e) { jsonData = {}; }
        var len = Array.isArray(jsonData) ? jsonData.length : Object.keys(jsonData).length;
        var prefix = Array.isArray(jsonData) ? 'array' : 'object';

        if (preElement.classList.contains("show")) {
            arrowElement.textContent = '\u25B6 ' + prefix + ':' + len;
            preElement.classList.remove("show");
        } else {
            arrowElement.textContent = '\u25BC ' + prefix + ':' + len;
            preElement.classList.add("show");
        }
    }

    window.toggleJson = toggleJson;
}
