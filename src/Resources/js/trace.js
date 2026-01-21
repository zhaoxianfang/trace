// ========================================
// Trace 调试工具 JavaScript
// ========================================

// 防止重复初始化
if (window.__traceInitialized) {
    console.warn('[Trace] Already initialized');
} else {
    window.__traceInitialized = true;

    // 全局变量
    var isClickAllowed = true; // 初始允许点击

    function trace_reset_allowed_value() {
        isClickAllowed = false; // 禁止后续点击
        setTimeout(() => {
            isClickAllowed = true;
        }, 500); // 500ms 后恢复
    }

    // 文档加载完成后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTrace);
    } else {
        // 如果文档已经加载完成，立即初始化
        initTrace();
    }

    // 初始化函数
    function initTrace() {
        try {
            initTabSwitch();
            initJsonDisplay();
            initTextExpansion();
            initClickHandlers();
        } catch (e) {
            console.error('[Trace] Initialization error:', e);
        }
    }

    // 初始化 Tab 切换
    function initTabSwitch() {
        const tabItems = document.querySelectorAll("#trace-tools-box .tabs-item");
        const tabContents = document.querySelectorAll("#trace-tools-box .tabs-content");

        if (tabItems.length === 0 || tabContents.length === 0) {
            return;
        }

        // 激活指定 Tab
        function activateTab(index) {
            tabItems.forEach((item, i) => {
                if (item && tabContents[i]) {
                    item.classList.toggle("active", i === index);
                    tabContents[i].classList.toggle("active", i === index);
                }
            });
        }

        // Tab 切换逻辑
        tabItems.forEach((tab, index) => {
            tab.addEventListener("click", () => activateTab(index));
        });

        // 初始化默认激活第一个 Tab
        if (tabItems.length > 0 && tabContents.length > 0) {
            activateTab(0);
        }
    }

    // 初始化 JSON 显示
    function initJsonDisplay() {
        const jsonElements = document.querySelectorAll("#trace-tools-box .json");
        const arrowElements = document.querySelectorAll("#trace-tools-box .json-arrow");
        const labelElements = document.querySelectorAll("#trace-tools-box .json-label");

        if (jsonElements.length === 0) {
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
                console.error('[Trace] Error initializing JSON element:', e);
            }
        });
    }

    // 初始化 JSON 显示
    function initializeJsonDisplay(jsonElement, arrowElement, _labelElement) {
        try {
            let jsonText = jsonElement.textContent.trim();
            jsonText = extractJson(jsonText);

            // 移除jsonText 中非json 字符串的部分
            const jsonData = JSON.parse(jsonData);

            // 计算 JSON 对象的长度
            const arrayLength = Array.isArray(jsonData) ? jsonData.length : Object.keys(jsonData).length;

            // 格式化 JSON 数据 为 带缩进的 JSON 字符串
            jsonElement.textContent = JSON.stringify(jsonData, null, 4);

            // 设置箭头的初始文本
            if (arrowElement) {
                arrowElement.textContent = `▶ array:${arrayLength}`;
            }
        } catch (e) {
            console.error("[Trace] Error initializing JSON display:", e);
        }
    }

    // 初始化文本展开/收起
    function initTextExpansion() {
        const labels = document.querySelectorAll('#trace-tools-box .json-label');

        labels.forEach(label => {
            if (!label) return;

            try {
                const text = label.textContent;
                if (text.length > 100) {
                    label.full = text;
                    label.short = text.substring(0, 100) + '...';
                    label.innerHTML = label.short + '<span class="expand-btn">展开</span>';
                    label.classList.add('truncated');

                    label.addEventListener('click', e => {
                        if (e.target.classList.contains('expand-btn')) {
                            const expanded = label.classList.toggle('expanded');
                            label.innerHTML = (expanded ? label.full : label.short) +
                                `<span class="expand-btn">${expanded ? '收起' : '展开'}</span>`;
                            expanded ? label.classList.remove('truncated') : label.classList.add('truncated');
                        }
                    });
                }
            } catch (e) {
                console.error('[Trace] Error initializing text expansion:', e);
            }
        });
    }

    // 初始化点击事件
    function initClickHandlers() {
        document.addEventListener('click', function (e) {
            try {
                const tabsLogoEvent = e.target.closest("#trace-tools-box .trace-logo");
                const closeButton = e.target.closest("#trace-tools-box .tabs-close");
                const tabsContainerDom = document.querySelector("#trace-tools-box .tabs-container");
                const tabsLogoDom = document.querySelector("#trace-tools-box .trace-logo");

                if (!tabsContainerDom || !tabsLogoDom) {
                    return;
                }

                if (tabsLogoEvent && isClickAllowed) { // 点击 Logo 展开 Tabs
                    tabsLogoDom.style.display = "none"; // 隐藏 Logo
                    tabsContainerDom.style.display = "flex"; // 显示 Tabs
                    trace_reset_allowed_value();
                }

                if (closeButton && isClickAllowed) { // 点击关闭按钮隐藏 Tabs
                    tabsContainerDom.style.display = "none"; // 隐藏 Tabs
                    tabsLogoDom.style.display = "block"; // 显示 Logo
                    trace_reset_allowed_value();
                }
            } catch (e) {
                console.error('[Trace] Error in click handler:', e);
            }
        });
    }

    // 提取最外层的 {} 或 [] 内容, 防止 dom 里面包含非json 的dom; eg:<pre class="json">[]<button class="copy-code-btn">复制</button></pre>
    function extractJson(text) {
        if (!text || typeof text !== 'string') {
            return null;
        }

        let stack = [];
        let start = -1;

        for (let i = 0; i < text.length; i++) {
            const char = text[i];
            if (char === '{' || char === '[') {
                if (stack.length === 0) {
                    start = i; // 开始位置
                }
                stack.push(char);
            } else if (char === '}' || char === ']') {
                if (stack.length === 0) continue;
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

    // 展开/收起 JSON , 在 html 中指定触发
    function toggleJson(arrowElement) {
        if (!arrowElement) {
            console.error('[Trace] toggleJson: arrowElement is null');
            return;
        }

        try {
            const preElement = arrowElement.nextElementSibling;
            const _labelElement = arrowElement.parentNode ? arrowElement.parentNode.previousElementSibling : null;

            if (!preElement || !preElement.getAttribute) {
                console.error('[Trace] toggleJson: preElement not found or invalid');
                return;
            }

            const jsonData = JSON.parse(preElement.getAttribute("data-original"));
            const arrayLength = Array.isArray(jsonData) ? jsonData.length : Object.keys(jsonData).length;

            if (preElement.classList.contains("show")) {
                // 收起
                arrowElement.textContent = `▶ array:${arrayLength}`;
                preElement.classList.remove("show");
            } else {
                // 展开
                arrowElement.textContent = `▼ array:${arrayLength}`;
                preElement.classList.add("show");
            }
        } catch (e) {
            console.error('[Trace] Error in toggleJson:', e);
        }
    }

    // 将函数暴露到全局作用域，以便 onclick 调用
    window.toggleJson = toggleJson;

    console.log('[Trace] Initialized successfully');
}

