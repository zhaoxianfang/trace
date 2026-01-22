// ========================================
// Trace 调试工具 JavaScript
// ========================================

// 防止重复初始化
if (window.__traceInitialized) {
    // console.warn('[Trace] Already initialized, skipping...');
} else {
    window.__traceInitialized = true;

    // 全局配置
    const CONFIG = {
        clickDebounceTime: 500, // 点击防抖时间(ms)
        textMaxLength: 100,    // 文本最大长度
        maxRetries: 3,          // 最大重试次数
    };

    // 全局状态
    let isClickAllowed = true;

    /**
     * 重置点击允许状态
     */
    function trace_reset_allowed_value() {
        isClickAllowed = false;
        setTimeout(() => {
            isClickAllowed = true;
        }, CONFIG.clickDebounceTime);
    }

    /**
     * 安全的 DOM 查询，带重试机制
     */
    function safeQuerySelector(selector, retries = CONFIG.maxRetries) {
        for (let i = 0; i < retries; i++) {
            const element = document.querySelector(selector);
            if (element) return element;
            // 等待 DOM 更新
            if (i < retries - 1) {
                new Promise(resolve => setTimeout(resolve, 10 * (i + 1)));
            }
        }
        return null;
    }

    /**
     * 安全的批量 DOM 查询
     */
    function safeQuerySelectorAll(selector) {
        try {
            return document.querySelectorAll(selector);
        } catch (e) {
            // console.error('[Trace] Error querying selector:', selector, e);
            return [];
        }
    }

    // 文档加载完成后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTrace);
    } else {
        initTrace();
    }

    /**
     * 初始化函数
     */
    function initTrace() {
        try {
            // console.log('[Trace] Initializing...');
            initTabSwitch();
            initJsonDisplay();
            initTextExpansion();
            initClickHandlers();
            initKeyboardShortcuts();
            // console.log('[Trace] Initialization completed successfully');
        } catch (e) {
            // console.error('[Trace] Initialization error:', e);
        }
    }

    /**
     * 初始化 Tab 切换
     */
    function initTabSwitch() {
        const tabItems = safeQuerySelectorAll("#trace-tools-box .tabs-item");
        const tabContents = safeQuerySelectorAll("#trace-tools-box .tabs-content");

        if (tabItems.length === 0 || tabContents.length === 0) {
            // console.warn('[Trace] No tabs found');
            return;
        }

        // 激活指定 Tab
        function activateTab(index) {
            try {
                tabItems.forEach((item, i) => {
                    if (item && tabContents[i]) {
                        const wasActive = item.classList.contains("active");
                        item.classList.toggle("active", i === index);
                        tabContents[i].classList.toggle("active", i === index);

                        // 只在状态改变时记录
                        // if (wasActive !== (i === index)) {
                        //     console.log(`[Trace] Tab ${i} ${i === index ? 'activated' : 'deactivated'}`);
                        // }
                    }
                });
            } catch (e) {
                // console.error('[Trace] Error activating tab:', index, e);
            }
        }

        // Tab 切换逻辑
        tabItems.forEach((tab, index) => {
            if (tab) {
                tab.addEventListener("click", (e) => {
                    e.stopPropagation();
                    activateTab(index);
                });
            }
        });

        // 初始化默认激活第一个 Tab
        if (tabItems.length > 0 && tabContents.length > 0) {
            activateTab(0);
        }
    }

    /**
     * 初始化 JSON 显示
     */
    function initJsonDisplay() {
        const jsonElements = safeQuerySelectorAll("#trace-tools-box .json");
        const arrowElements = safeQuerySelectorAll("#trace-tools-box .json-arrow");
        const labelElements = safeQuerySelectorAll("#trace-tools-box .json-label");

        if (jsonElements.length === 0) {
            // console.warn('[Trace] No JSON elements found');
            return;
        }

        jsonElements.forEach((jsonElement, index) => {
            if (!jsonElement) return;

            try {
                let jsonText = jsonElement.textContent.trim();
                jsonText = extractJson(jsonText);

                if (!jsonText) {
                    jsonText = '[]';
                }

                jsonElement.setAttribute("data-original", jsonText);

                if (arrowElements[index]) {
                    initializeJsonDisplay(jsonElement, arrowElements[index], labelElements[index]);
                }
            } catch (e) {
                // console.error('[Trace] Error initializing JSON element at index', index, ':', e);
            }
        });
    }

    /**
     * 初始化 JSON 显示
     */
    function initializeJsonDisplay(jsonElement, arrowElement, _labelElement) {
        try {
            // 从 data-original 属性获取已处理的 JSON 文本
            let jsonText = jsonElement.getAttribute("data-original");
            
            if (!jsonText) {
                // console.warn('[Trace] No original JSON data found');
                return;
            }

            // 解析 JSON
            let jsonData;
            try {
                jsonData = JSON.parse(jsonText);
            } catch (parseError) {
                // console.warn('[Trace] Failed to parse JSON:', parseError);
                jsonData = { error: 'Invalid JSON' };
            }

            // 计算 JSON 对象的长度
            const arrayLength = Array.isArray(jsonData) ? jsonData.length : Object.keys(jsonData).length;

            // 格式化 JSON 数据为带缩进的 JSON 字符串
            jsonElement.textContent = JSON.stringify(jsonData, null, 4);

            // 设置箭头的初始文本
            if (arrowElement) {
                const prefix = Array.isArray(jsonData) ? 'array' : 'object';
                arrowElement.textContent = `▶ ${prefix}:${arrayLength}`;
            }
        } catch (e) {
            // console.error("[Trace] Error initializing JSON display:", e);
        }
    }

    /**
     * 初始化文本展开/收起
     */
    function initTextExpansion() {
        const labels = safeQuerySelectorAll('#trace-tools-box .json-label');

        labels.forEach(label => {
            if (!label) return;

            try {
                const text = label.textContent;
                if (text.length > CONFIG.textMaxLength) {
                    label.full = text;
                    label.short = text.substring(0, CONFIG.textMaxLength) + '...';
                    label.innerHTML = label.short + '<span class="expand-btn">展开</span>';
                    label.classList.add('truncated');

                    label.addEventListener('click', (e) => {
                        if (e.target.classList.contains('expand-btn')) {
                            e.stopPropagation();
                            const expanded = label.classList.toggle('expanded');
                            label.innerHTML = (expanded ? label.full : label.short) +
                                `<span class="expand-btn">${expanded ? '收起' : '展开'}</span>`;
                            expanded ? label.classList.remove('truncated') : label.classList.add('truncated');
                        }
                    });
                }
            } catch (e) {
                // console.error('[Trace] Error initializing text expansion for label:', e);
            }
        });
    }

    /**
     * 初始化点击事件
     */
    function initClickHandlers() {
        document.addEventListener('click', (e) => {
            try {
                const tabsLogoEvent = e.target.closest("#trace-tools-box .trace-logo");
                const closeButton = e.target.closest("#trace-tools-box .tabs-close");
                const tabsContainerDom = safeQuerySelector("#trace-tools-box .tabs-container");
                const tabsLogoDom = safeQuerySelector("#trace-tools-box .trace-logo");

                if (!tabsContainerDom || !tabsLogoDom) {
                    // console.warn('[Trace] Elements not found - container:', !!tabsContainerDom, 'logo:', !!tabsLogoDom);
                    return;
                }

                // console.log('[Trace] Click detected - logo:', !!tabsLogoEvent, 'close:', !!closeButton, 'allowed:', isClickAllowed);

                if (tabsLogoEvent && isClickAllowed) {
                    // 点击 Logo 展开 Tabs
                    tabsLogoDom.classList.add('hidden');
                    tabsContainerDom.classList.add('visible');
                    // console.log('[Trace] Panel opened - logo hidden, container shown');
                    trace_reset_allowed_value();
                }

                if (closeButton && isClickAllowed) {
                    // 点击关闭按钮隐藏 Tabs
                    tabsContainerDom.classList.remove('visible');
                    tabsLogoDom.classList.remove('hidden');
                    // console.log('[Trace] Panel closed - container hidden, logo shown');
                    trace_reset_allowed_value();
                }
            } catch (e) {
                // console.error('[Trace] Error in click handler:', e);
            }
        });
    }

    /**
     * 初始化键盘快捷键
     */
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            try {
                // ESC 键关闭面板
                if (e.key === 'Escape') {
                    const tabsContainerDom = safeQuerySelector("#trace-tools-box .tabs-container");
                    const tabsLogoDom = safeQuerySelector("#trace-tools-box .trace-logo");

                    if (tabsContainerDom && tabsLogoDom && tabsContainerDom.classList.contains('visible')) {
                        tabsContainerDom.classList.remove('visible');
                        tabsLogoDom.classList.remove('hidden');
                        // console.log('[Trace] Panel closed via ESC key');
                    }
                }
            } catch (e) {
                // console.error('[Trace] Error in keyboard shortcut handler:', e);
            }
        });
    }

    /**
     * 提取最外层的 {} 或 [] 内容
     * 防止 DOM 里面包含非 JSON 的 DOM 元素
     */
    function extractJson(text) {
        if (!text || typeof text !== 'string') {
            return null;
        }

        text = text.trim();

        if (text.length === 0) {
            return null;
        }

        let stack = [];
        let start = -1;

        for (let i = 0; i < text.length; i++) {
            const char = text[i];

            if (char === '{' || char === '[') {
                if (stack.length === 0) {
                    start = i;
                }
                stack.push(char);
            } else if (char === '}' || char === ']') {
                if (stack.length === 0) {
                    continue;
                }

                const open = stack.pop();
                // 检查括号是否匹配
                if ((open === '{' && char !== '}') || (open === '[' && char !== ']')) {
                    return null; // 不匹配，无效
                }

                if (stack.length === 0) {
                    // 成功找到一个完整的 JSON 字符串
                    return text.slice(start, i + 1);
                }
            }
        }

        return null; // 没有找到有效 JSON
    }

    /**
     * 展开/收起 JSON
     * 在 HTML 中指定触发
     */
    function toggleJson(arrowElement) {
        if (!arrowElement) {
            // console.error('[Trace] toggleJson: arrowElement is null');
            return;
        }

        try {
            const preElement = arrowElement.nextElementSibling;
            const _labelElement = arrowElement.parentNode ? arrowElement.parentNode.previousElementSibling : null;

            if (!preElement || !preElement.getAttribute) {
                // console.error('[Trace] toggleJson: preElement not found or invalid');
                return;
            }

            const originalJson = preElement.getAttribute("data-original");
            if (!originalJson) {
                // console.error('[Trace] toggleJson: no original JSON data');
                return;
            }

            let jsonData;
            try {
                jsonData = JSON.parse(originalJson);
            } catch (parseError) {
                // console.error('[Trace] toggleJson: failed to parse JSON:', parseError);
                return;
            }

            const arrayLength = Array.isArray(jsonData) ? jsonData.length : Object.keys(jsonData).length;
            const prefix = Array.isArray(jsonData) ? 'array' : 'object';

            if (preElement.classList.contains("show")) {
                // 收起
                arrowElement.textContent = `▶ ${prefix}:${arrayLength}`;
                preElement.classList.remove("show");
                // console.log('[Trace] JSON collapsed');
            } else {
                // 展开
                arrowElement.textContent = `▼ ${prefix}:${arrayLength}`;
                preElement.classList.add("show");
                // console.log('[Trace] JSON expanded');
            }
        } catch (e) {
            // console.error('[Trace] Error in toggleJson:', e);
        }
    }

    // 将函数暴露到全局作用域，以便 onclick 调用
    window.toggleJson = toggleJson;

    // console.log('[Trace] Module loaded successfully');
}
