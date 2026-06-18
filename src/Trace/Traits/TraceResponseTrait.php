<?php

namespace zxf\Trace\Traits;

use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Trace响应渲染Trait
 *
 * 提供Trace调试数据的HTML渲染功能，包括：
 * - 渲染调试面板到响应页面
 * - 处理SQL分组展示
 * - 注入CSS和JS资源
 * - 支持AJAX和JSON响应
 *
 * @package zxf\Trace\Traits
 */
trait TraceResponseTrait
{
    // 编辑器配置缓存
    protected static ?string $editorConfig = null;

    /**
     * 生成编辑器链接
     *
     * @param string $file 文件路径
     * @param int $line 行号
     * @param string|null $displayText 显示文本（可选）
     * @return string HTML链接
     */
    protected function generateEditorLink(string $file, int $line, ?string $displayText = null): string
    {
        if (self::$editorConfig === null) {
            self::$editorConfig = config('trace.editor') ?? 'phpstorm';
        }

        $editor = self::$editorConfig;
        $fileName = $this->escapeHtml($displayText ?? str_replace(base_path() ?: '', '', $file));
        $safeEditor = $this->escapeHtml($editor);

        return '<a href="'.$safeEditor.'://open?file='.urlencode($file).'&amp;line='.$line.'" class="phpdebugbar-link">'.$fileName.'#'.$line.'</a>';
    }

    /**
     * 返回在页面只渲染调试页面
     *
     * 优先使用 Blade 视图，失败时回退到动态渲染
     *
     * @param array $trace 跟踪数据
     * @return string HTML内容
     */
    public function randerPage($trace): string
    {
        // 尝试使用 Blade 视图
        if ($this->canUseBladeViews()) {
            try {
                return $this->renderBladePanel($trace);
            } catch (\Throwable $e) {
                // Blade 渲染失败，回退到动态渲染
            }
        }

        // 回退方案：动态渲染
        return $this->renderDynamicPanel($trace);
    }

    /**
     * 检查是否可以使用 Blade 视图
     *
     * @return bool
     */
    protected function canUseBladeViews(): bool
    {
        try {
            if (!function_exists('view') || !function_exists('app')) {
                return false;
            }

            $app = app();
            if (!$app->bound('view')) {
                return false;
            }

            // 检查 trace 命名空间是否可用
            $view = $app->make('view');
            return $view->exists('trace::trace-panel');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 使用 Blade 视图渲染调试面板
     *
     * 将 $trace 原始数据转换为 blade 面板所需的结构化数据：
     * - tabs: [tab_key => tab_label] 标签列表
     * - contents: [tab_key => pre-rendered HTML] 预渲染的标签内容
     * - badges: [tab_key => count] 标签上的数字角标
     * - performance: ['time' => ..., 'memory' => ...] 性能数据
     *
     * @param array $trace 跟踪数据（keyed by tab name）
     * @return string HTML内容
     */
    protected function renderBladePanel(array $trace): string
    {
        // 获取缓存的编辑器配置
        if (self::$editorConfig === null) {
            self::$editorConfig = config('trace.editor') ?? 'phpstorm';
        }
        $editor = self::$editorConfig;

        $tabs = [];
        $contents = [];
        $badges = [];
        $performance = $this->extractPerformanceFromTrace($trace);

        $tabIndex = 0;
        foreach ($trace as $tabLabel => $items) {
            $tabKey = 'tab' . ($tabIndex + 1);
            $tabs[$tabKey] = $tabLabel;

            // 检查是否为纯空状态提示（只有一个 is_empty_tips 条目）
            $isEmptyTips = is_array($items) && count($items) === 1
                && isset($items[array_key_first($items)]['is_empty_tips'])
                && $items[array_key_first($items)]['is_empty_tips'];

            if ($isEmptyTips) {
                // 空状态：直接渲染居中提示，不包裹 <ul>
                $tipsItem = $items[array_key_first($items)];
                $contents[$tabKey] = $this->renderDynamicPanelItem(0, $tipsItem, $editor);
            } else {
                // 渲染该标签下的所有数据项为 HTML
                $contentHtml = '<ul class="trace-list">';
                $itemCount = 0;
                foreach ($items as $key => $item) {
                    // 跳过可能的 is_empty_tips 残余
                    if (is_array($item) && isset($item['is_empty_tips']) && $item['is_empty_tips']) {
                        continue;
                    }
                    $itemHtml = $this->renderDynamicPanelItem($key, $item, $editor);
                    $contentHtml .= '<li class="trace-list-item">' . $itemHtml . '</li>';
                    $itemCount++;
                }
                $contentHtml .= '</ul>';
                $contents[$tabKey] = $contentHtml;

                // 统计数量作为角标
                if ($itemCount > 0) {
                    $badges[$tabKey] = $itemCount;
                }
            }

            $tabIndex++;
        }

        // 使用新的 panel 视图（仅限内部使用）
        $view = view('trace::panel', [
            'trace' => $trace,
            'tabs' => $tabs,
            'badges' => $badges,
            'contents' => $contents,
            'performance' => $performance,
        ]);

        return $view->render();
    }

    /**
     * 从 Trace 数据中提取性能指标
     *
     * 扫描 Base 标签页中的数据，提取运行时间和内存消耗，
     * 用于面板工具栏的实时显示
     *
     * @param array $trace
     * @return array ['time' => string, 'memory' => string]
     */
    protected function extractPerformanceFromTrace(array $trace): array
    {
        $performance = ['time' => '0ms', 'memory' => '0MB'];

        foreach ($trace as $tabLabel => $items) {
            // 查找 Base 标签页（匹配 "Base" 或 "Base (X)" 格式）
            if (!is_array($items) || !str_contains($tabLabel, 'Base')) {
                continue;
            }

            foreach ($items as $key => $value) {
                $label = is_string($key) ? $key : (is_array($value) ? ($value['label'] ?? '') : '');

                if ($label === '运行时间') {
                    $rawTime = is_array($value) ? ($value['label'] ?? '') : (string)$value;
                    if (is_string($rawTime) && !is_array($rawTime)) {
                        $performance['time'] = $rawTime;
                    }
                }

                if ($label === '内存消耗') {
                    $rawMem = is_array($value) ? ($value['label'] ?? '') : (string)$value;
                    if (is_string($rawMem) && !is_array($rawMem)) {
                        $performance['memory'] = $rawMem;
                    }
                }
            }
            break; // 找到 Base 后退出
        }

        return $performance;
    }

    /**
     * 动态渲染调试面板（Blade 不可用时的回退方案）
     *
     * @param array $trace 跟踪数据
     * @return string HTML内容
     */
    protected function renderDynamicPanel(array $trace): string
    {
        // 获取缓存的编辑器配置
        if (self::$editorConfig === null) {
            self::$editorConfig = config('trace.editor') ?? 'phpstorm';
        }

        $editor = self::$editorConfig;

        // 构建 Tab 头部
        $tabHeaders = '';
        $tabNames = array_keys($trace);
        foreach ($tabNames as $key => $name) {
            $tabKey = $key + 1;
            $activeClass = $key < 1 ? 'active' : '';
            $isSelected = $key < 1 ? 'true' : 'false';
            $safeName = $this->escapeHtml($name);
            $tabHeaders .= "<div class='tabs-item {$activeClass}' data-tab='tab{$tabKey}' tabindex='0' role='tab' aria-selected='{$isSelected}'>{$safeName}</div>";
        }

        // 构建 Tab 内容
        $tabContents = '';
        $tabIndex = 0;
        foreach ($trace as $key => $tabs) {
            $tabKey = ++$tabIndex;
            $active = $tabIndex < 2 ? 'active' : '';
            $contentItems = '';

            // 检查是否为纯空状态提示
            $isEmptyTips = is_array($tabs) && count($tabs) === 1
                && isset($tabs[array_key_first($tabs)]['is_empty_tips'])
                && $tabs[array_key_first($tabs)]['is_empty_tips'];

            if ($isEmptyTips) {
                $tipsItem = $tabs[array_key_first($tabs)];
                $contentItems = $this->renderDynamicPanelItem(0, $tipsItem, $editor);
            } else {
                foreach ($tabs as $k => $item) {
                    if (is_array($item) && isset($item['is_empty_tips']) && $item['is_empty_tips']) {
                        continue;
                    }
                    $itemHtml = $this->renderDynamicPanelItem($k, $item, $editor);
                    $contentItems .= "<li>{$itemHtml}</li>";
                }
                $contentItems = "<ul>{$contentItems}</ul>";
            }

            $tabContents .= "<div id=\"tab{$tabKey}\" class=\"tabs-content {$active}\" role=\"tabpanel\" aria-labelledby=\"tab{$tabKey}\">{$contentItems}</div>";
        }

        // Logo base64 图片
        $logoBase64 = 'iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAYAAACNiR0NAAAAAXNSR0IArs4c6QAAAcBJREFUOE/F1MtKAlEYB/DvaI1OlJIuhBYKFbSzi9lKcIJadnkIoQeoTYvw0mu0laBlPYAOQUG2cFGEQdioQXbTUcsy9cQZm+HkXBRcdEAGjt/8zn/OzHcQ9DEwH4qQMhQ8kK5GAxn9iRMhDsw4DBi4Th2K9kI1QTXUvaw+rAI7j4fDvR5NL7ECSqlMOKEJ2WcAxIz2GgiSgBEvb4UE5g8DHGsdizgdE8Huu7B3B4CAZOROAAnHf0rE50pauCnA7N75vLTLMohwJ52VtfEExp51APeadqpfOHuV40vFshTCF0tJlgokkzb/dnF0atOlt49NsdDIHu3mag+303KNIch6t8BsnwTLECNaXIt2+aZW6b7+ep2sm0fGHU9ncfh8EZQ1+wLlaqb9VawIwjCB5LmBwGb1sY4/zCy9Bf8LMp5VYNwrSqCBExKJvBQCkysNiplTIL/uYfhS6GICmpxLb9W7rEMLkr49DMmF/dSy8h3KQD4eiCCk7uNGrZ0uFZpzOr0X9cUulGNNdTiQNoQ2cDSsBdKp6IV0z0M6LQ0SqGXCUX/0MqmV2PCAlfo8Hoh8v7c2yvlm2QiS8Z6gXj/rzf8AmFQQJJO/2LAAAAAASUVORK5CYII=';

        return <<<HTML
<div id="trace-tools-box">
    <div class="trace-logo">
        <img src="data:image/png;base64,{$logoBase64}" alt="Logo" style="height: 18px;" class="logo">
        <span class="title">Trace</span>
    </div>
    <div class="tabs-container">
        <div class="tabs-header">
            <img src="data:image/png;base64,{$logoBase64}" alt="Logo" class="tabs-logo-small">
            <div class="tabs-menu">{$tabHeaders}</div>
            <div class="tabs-close" title="关闭调试面板 (ESC)">×</div>
        </div>
        {$tabContents}
    </div>
</div>
HTML;
    }

    /**
     * 渲染动态面板单项
     *
     * @param string|int $key 键名
     * @param mixed $item 数据项
     * @param string $editor 编辑器
     * @return string HTML内容
     */
    protected function renderDynamicPanelItem($key, $item, string $editor): string
    {
        // SQL 分组处理
        if (is_array($item) && isset($item['type']) && $item['type'] == 'sql_group') {
            return $this->renderDynamicSqlGroup($item);
        }

        // 模型数据项处理（结构化展示模型操作详情）
        if (is_array($item) && isset($item['type']) && $item['type'] == 'model_item') {
            return $this->renderDynamicModelItem($item, $editor);
        }

        // 视图数据项处理（可展开查看视图传递的所有参数）
        if (is_array($item) && isset($item['type']) && $item['type'] == 'view_item') {
            return $this->renderDynamicViewItem($item, $editor);
        }

        // 文件链接类型（显示 key 标签 + IDE 链接）
        if (is_array($item) && isset($item['type']) && $item['type'] == 'file_link') {
            // 优先使用 item 中的 label，其次使用 key（做友好化处理）
            $label = '';
            if (isset($item['label_override'])) {
                $label = $this->escapeHtml($item['label_override']);
            } elseif (is_string($key)) {
                // key 友好化映射
                $labelMap = [
                    'file' => 'Controller File',
                    'route_file' => 'Route File',
                    'route_definition' => 'Route Definition',
                ];
                $label = $this->escapeHtml($labelMap[$key] ?? $key);
            }
            $displayText = $item['display'] ?? '';
            $filePath = $item['file_path'] ?? '';
            $line = (int)($item['line'] ?? 1);
            $labelClass = isset($item['label_class']) ? ' ' . $item['label_class'] : '';
            $html = '';
            if ($label) {
                $html .= "<span class='json-label{$labelClass}'>{$label}</span>";
            }
            $html .= "<span class='json-string-content' style='font-size:13px;'>";
            $html .= $this->generateEditorLink($filePath, $line, $displayText);
            $html .= "</span>";
            return $html;
        }

        // 空状态提示（如 Messages 标签无内容时）
        // 注意：返回值不包含 <li>，由外层统一包裹
        if (is_array($item) && isset($item['is_empty_tips']) && $item['is_empty_tips']) {
            $message = $this->escapeHtml($item['message'] ?? '');
            $tips = $this->escapeHtml($item['tips'] ?? '');
            $tipsHtml = $tips ? "<div style='color: #718096; font-size: 12px; margin-top: 6px;'>{$tips}</div>" : '';
            return "<div style='text-align: center; padding: 40px 20px;'><div style='color: #a0aec0; font-size: 14px; margin-bottom: 4px;'>{$message}</div>{$tipsHtml}</div>";
        }

        // 带有原始 HTML 的内容（如异常文件链接或代码块）
        // 注意：返回值不包含 <li>，由外层统一包裹
        if (is_array($item) && isset($item['raw_html']) && $item['raw_html']) {
            // 显示 key 标签
            $html = '';
            if (is_string($key) && $key !== '0') {
                $safeKey = $this->escapeHtml($key);
                $html .= "<span class='json-label'>{$safeKey}</span>";
            }
            $content = $item['content'];
            // 如果包含错误行代码高亮，添加样式
            if (is_string($content) && str_contains($content, 'error-line-code')) {
                $html .= "<pre style='background:#1e1e2e;color:#cdd6f4;padding:15px;border-radius:6px;overflow-x:auto;font-family:Consolas,Monaco,Courier New,monospace;font-size:13px;line-height:1.6;margin:5px 0;'>{$content}</pre>";
                return $html;
            }
            $html .= $content;
            return $html;
        }

        // Trace 数据类型
        if (is_array($item) && !empty($item['type']) && $item['type'] == 'trace') {
            return $this->renderDynamicTraceItem($item, $editor);
        }

        $html = '';

        // 左侧 label
        if (is_array($item) && !empty($item['label'])) {
            $label = $this->escapeHtml($item['label']);
            $html .= "<span class='json-label'>{$label}</span>";
        } elseif (is_string($key)) {
            $safeKey = $this->escapeHtml($key);
            $html .= "<span class='json-label'>{$safeKey}</span>";
        }

        // 中间内容
        if (!is_array($item)) {
            $class = is_numeric($key) ? 'json-label' : 'json-string-content';
            if (is_scalar($item) || is_null($item)) {
                $value = $this->escapeHtml(format_param($item));
                $html .= "<div class='{$class}'>{$value}</div>";
            } else {
                $typeName = ucfirst(gettype($item));
                $className = get_class($item);
                $html .= "<div class='{$class}'>{$typeName}:{$className}</div>";
            }
        } else {
            $diffKeys = array_diff(array_keys($item), ['label', 'right', 'has_html', 'message', 'tips', 'raw_html', 'content', 'is_empty_tips']);
            if (!empty($diffKeys)) {
                $jsonString = $this->escapeHtml(json_encode($item, JSON_UNESCAPED_UNICODE));
                $html .= "<div class=\"json-arrow-pre-wrapper\"><span class=\"json-arrow\" onclick=\"toggleJson(this)\" role=\"button\" tabindex=\"0\" aria-expanded=\"false\">▶</span><pre class=\"json\">{$jsonString}</pre></div>";
            } elseif (empty($item)) {
                $html .= "<span class='json-string-content'>[]</span>";
            }
        }

        // 右侧 right
        if (is_array($item) && !empty($item['right'])) {
            $right = $this->escapeHtml((string)$item['right']);
            $html .= "<span class='json-right'>{$right}</span>";
        }

        return $html;
    }

    /**
     * 渲染动态视图数据项
     *
     * 每个视图项显示视图名称、可点击的 IDE 文件链接和参数数量，
     * 点击展开后可查看传递到该视图的所有参数/变量明细
     *
     * @param array $item 视图数据项
     * @param string $editor 编辑器协议
     * @return string HTML
     */
    protected function renderDynamicViewItem(array $item, string $editor): string
    {
        $label = $this->escapeHtml($item['label'] ?? '');
        $viewPath = $item['view_path'] ?? '';
        $viewPathRel = $this->escapeHtml($item['view_path_rel'] ?? '');
        $viewParams = $item['view_params'] ?? [];
        $paramCount = (int) ($item['param_count'] ?? 0);

        // 视图名称标签
        $html = "<span class='json-label'>{$label}</span>";

        // 内容区域：文件路径 IDE 链接 + 参数展开箭头（同一行）
        $contentHtml = '';

        if (!empty($viewPath)) {
            $contentHtml .= $this->generateEditorLink($viewPath, 1, $viewPathRel ?: basename($viewPath));
        }

        // 参数展开箭头内嵌在内容区域内，与文件路径保持同一行
        if ($paramCount > 0 && !empty($viewParams)) {
            $jsonString = $this->escapeHtml(json_encode($viewParams, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $contentHtml .= '<span class="json-arrow-pre-wrapper json-arrow-pre-wrapper-inline">';
            $contentHtml .= '<span class="json-arrow" onclick="toggleJson(this)" role="button" tabindex="0" aria-expanded="false" title="展开查看视图参数">▶</span>';
            $contentHtml .= '<pre class="json">' . $jsonString . '</pre>';
            $contentHtml .= '</span>';
        } elseif ($paramCount > 0) {
            $contentHtml .= '<span class="json-arrow-pre-wrapper json-arrow-pre-wrapper-inline">';
            $contentHtml .= '<span class="json-arrow" onclick="toggleJson(this)" role="button" tabindex="0" aria-expanded="false" title="展开查看视图参数">▶</span>';
            $contentHtml .= '<pre class="json">[]</pre>';
            $contentHtml .= '</span>';
        }

        $html .= "<span class='json-string-content' style='font-size:12px;display:flex;align-items:flex-start;gap:6px;flex-wrap:wrap;'>" . $contentHtml . "</span>";

        // 参数数量标签
        if ($paramCount > 0) {
            $html .= "<span class='json-right'>" . $paramCount . " vars</span>";
        }

        return $html;
    }

    /**
     * 渲染动态模型数据项
     *
     * 显示模型类名（可点击 IDE 链接）、模型 ID、操作次数和操作类型标记
     *
     * @param array $item 模型数据项
     * @param string $editor 编辑器协议
     * @return string HTML
     */
    protected function renderDynamicModelItem(array $item, string $editor): string
    {
        $modelClass = $this->escapeHtml($item['model_class'] ?? '');
        $modelId = $this->escapeHtml($item['model_id'] ?? '');
        $eventCount = (int)($item['event_count'] ?? 0);
        $hasQueries = !empty($item['has_db_queries']);
        $hasWrites = !empty($item['has_db_writes']);
        $referenceOnly = !empty($item['reference_only']);
        $filePath = $item['file_path'] ?? '';
        $events = $item['events'] ?? [];

        // 左侧：模型类名标签
        $html = "<span class='json-label'>" . $modelClass . "</span>";

        // 中间：文件路径 IDE 链接 + 模型 ID
        $centerHtml = '';
        if (!empty($filePath)) {
            $relativePath = trim(str_replace(base_path() ?: '', '', $filePath), '/');
            $centerHtml .= $this->generateEditorLink($filePath, 1, $relativePath);
        }
        $centerHtml .= ' <span style="color:#8b949e;font-size:11px;">#' . $modelId . '</span>';
        $html .= "<span class='json-string-content' style='font-size:12px;'>" . $centerHtml . "</span>";

        // 右侧：操作标记
        $badges = [];
        if ($hasQueries && $hasWrites) {
            $badges[] = '<span class="model-badge model-badge-mixed">查询+写入</span>';
        } elseif ($hasQueries) {
            $badges[] = '<span class="model-badge model-badge-query">查询</span>';
        } elseif ($hasWrites) {
            $badges[] = '<span class="model-badge model-badge-write">写入</span>';
        } elseif ($referenceOnly) {
            $badges[] = '<span class="model-badge model-badge-ref">关联</span>';
        }

        // 事件列表简写
        $eventLabels = array_map(function($e) {
            return match($e) {
                'retrieved' => 'R',
                'creating' => 'C+',
                'created' => 'C',
                'updating' => 'U+',
                'updated' => 'U',
                'deleting' => 'D+',
                'deleted' => 'D',
                'saving' => 'S+',
                'saved' => 'S',
                default => substr($e, 0, 1),
            };
        }, array_unique($events));
        $eventStr = implode('', $eventLabels);

        $rightHtml = implode(' ', $badges);
        $rightHtml .= ' <span style="color:#8b949e;font-size:11px;">「' . $eventCount . '次」</span>';
        if ($eventStr) {
            $rightHtml .= ' <span style="color:#58a6ff;font-size:10px;">[' . $eventStr . ']</span>';
        }
        $html .= "<span class='json-right'>" . $rightHtml . "</span>";

        return $html;
    }

    /**
     * 渲染动态 SQL 分组
     *
     * @param array $group 分组数据
     * @return string HTML内容
     */
    protected function renderDynamicSqlGroup(array $group): string
    {
        $groupName = $this->escapeHtml($group['name'] ?? '');
        $groupClass = $this->escapeHtml($group['class'] ?? 'sql-group');
        $sqlCount = (int) ($group['count'] ?? 0);
        $collapsed = ($group['collapsed'] ?? false) ? 'collapsed' : '';

        $sqlItems = '';
        $sqls = is_array($group['sqls'] ?? null) ? $group['sqls'] : [];
        foreach ($sqls as $sqlItem) {
            $sql = $this->escapeHtml($sqlItem['label'] ?? '');
            $time = $this->escapeHtml($sqlItem['right'] ?? '-');
            $sqlItems .= "<li><span class=\"json-label\">{$sql}</span><span class=\"json-right\">{$time}</span></li>";
        }

        return <<<SQL
<div class="sql-group {$groupClass} {$collapsed}">
    <div class="sql-group-header">
        <div class="sql-group-title">
            <span>{$groupName}</span>
            <span class="sql-group-count">{$sqlCount}条</span>
        </div>
        <span class="sql-group-toggle">▼</span>
    </div>
    <ul class="sql-group-content">{$sqlItems}</ul>
</div>
SQL;
    }

    /**
     * 渲染动态 Trace 数据项
     *
     * @param array $data Trace数据
     * @param string $editor 编辑器
     * @return string HTML内容
     */
    protected function renderDynamicTraceItem(array $data, string $editor): string
    {
        $filePath = $data['file_path'] ?? '';
        $line = (int) ($data['line'] ?? 1);
        $local = $this->escapeHtml($data['local'] ?? '');
        $var = $data['var'] ?? null;

        $safeEditor = $this->escapeHtml($editor);
        $html = '<span class="json-label">' . $local . '</span>';
        $html .= '<span class="json-string-content" style="font-size:13px;">';
        $html .= '<a href="' . $safeEditor . '://open?file=' . urlencode($filePath) . '&amp;line=' . $line . '" class="phpdebugbar-link">' . $this->escapeHtml($data['base_path'] ?? $filePath) . '#' . $line . '</a>';
        $html .= '</span>';

        if (is_array($var) && !empty($var)) {
            $jsonString = $this->escapeHtml(json_encode($var, JSON_UNESCAPED_UNICODE));
            $html .= '<span class="json-arrow-pre-wrapper json-arrow-pre-wrapper-inline">';
            $html .= '<span class="json-arrow" onclick="toggleJson(this)">▶</span>';
            $html .= '<pre class="json">' . $jsonString . '</pre>';
            $html .= '</span>';
        } elseif (is_array($var)) {
            $html .= "<span class='json-string-content'>[]</span>";
        } else {
            if (is_scalar($var) || is_null($var)) {
                $value = $this->escapeHtml(format_param($var));
                $html .= "<span class='json-string-content'>{$value}</span>";
            } else {
                $value = $this->escapeHtml((string) $var);
                $html .= "<span class='json-string-content'>{$value}</span>";
            }
        }

        // 右侧类型标记
        $rightType = $data['right'] ?? '';
        if ($rightType) {
            $html .= "<span class='json-right'>" . $this->escapeHtml($rightType) . "</span>";
        }

        return $html;
    }

    /**
     * 渲染SQL分组（向后兼容，优先使用视图组件）
     *
     * @param  array  $group 分组数据
     * @return string HTML字符串
     */
    protected function renderSqlGroup(array $group): string
    {
        // 优先尝试使用 Blade 组件
        if ($this->canUseBladeViews()) {
            try {
                return view('trace::components.sql-group', [
                    'name' => $group['name'] ?? 'SQL Group',
                    'class' => $group['class'] ?? 'sql-group',
                    'collapsed' => $group['collapsed'] ?? false,
                    'count' => $group['count'] ?? 0,
                    'sqls' => $group['sqls'] ?? [],
                ])->render();
            } catch (\Throwable $e) {
                // Blade 渲染失败，回退到动态渲染
            }
        }

        return $this->renderDynamicSqlGroup($group);
    }

    /**
     * HTML 实体编码
     *
     * @param string $text
     * @return string
     */
    protected function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * 处理 Trace 数据（向后兼容，优先使用视图组件）
     *
     * @param array $data Trace数据
     * @return string HTML字符串
     */
    protected function handleTraceData($data = []): string
    {
        // 获取缓存的编辑器配置
        if (self::$editorConfig === null) {
            self::$editorConfig = config('trace.editor') ?? 'phpstorm';
        }

        $editor = self::$editorConfig;

        // 优先尝试使用 Blade 组件
        if ($this->canUseBladeViews()) {
            try {
                return view('trace::components.trace-item', [
                    'data' => array_merge($data, ['editor' => $editor]),
                ])->render();
            } catch (\Throwable $e) {
                // Blade 渲染失败，回退到动态渲染
            }
        }

        return $this->renderDynamicTraceItem($data, $editor);
    }

    /**
     * 把trace数据渲染到响应的html中
     *
     * @param  Request  $request  HTTP 请求对象
     * @param  SymfonyResponse  $response  HTTP 响应对象（支持多种响应类型）
     * @return SymfonyResponse 返回处理后的响应对象
     */
    public function renderTraceStyleAndScript(Request $request, SymfonyResponse $response): SymfonyResponse
    {
        if (! is_enable_trace()) {
            return $response;
        }

        $traceContent = $this->output($response);

        if (empty($traceContent)) {
            return $response;
        }

        $content = $response->getContent();

        // 检查内容是否为空
        if (empty($content)) {
            return $response;
        }

        // 处理非 GET 请求
        if (! $request->isMethod('get')) {
            try {
                $decodedContent = json_decode($content, true);
                if (is_array($decodedContent)) {
                    $decodedContent['_debugger'] = $traceContent;
                    $content = json_encode($decodedContent, JSON_UNESCAPED_UNICODE);
                    $response->setContent($content);
                    $response->headers->remove('Content-Length');
                }
            } catch (Exception) {
                // JSON 解析失败，不处理
            }
            return $response;
        }

        // 安全获取路由 URL
        try {
            $cssRoute = route('zxf.trace.trace.css');
            $jsRoute = route('zxf.trace.trace.js');
        } catch (\Exception) {
            // 路由不存在，使用备用路径
            $cssRoute = '/zxf/trace/assets/trace.css';
            $jsRoute = '/zxf/trace/assets/trace.js';
        }

        // 移除协议部分，使用协议相对 URL
        $cssRoute = preg_replace('/\Ahttps?:/', '', $cssRoute);
        $jsRoute = preg_replace('/\Ahttps?:/', '', $jsRoute);

        $style = "<link rel='stylesheet' type='text/css' property='stylesheet' href='{$cssRoute}' data-turbolinks-eval='false' data-turbo-eval='false'>";
        $script = "<script src='{$jsRoute}' type='text/javascript' data-turbolinks-eval='false' data-turbo-eval='false'></script>";

        // 尝试找到 </head> 标签的位置（不区分大小写）
        $posCss = strripos($content, '</head>');
        $posHeadCase = strripos($content, '</HEAD>');

        // 使用找到的位置（区分大小写优先）
        $insertCssPos = max($posCss, $posHeadCase);

        if ($insertCssPos !== false) {
            $content = substr($content, 0, $insertCssPos).PHP_EOL.$style.PHP_EOL.substr($content, $insertCssPos);
        } else {
            // 如果没有找到 </head> 标签，尝试其他方案
            // 1. 尝试在 <head> 标签后插入
            $posHeadStart = stripos($content, '<head');
            if ($posHeadStart !== false) {
                $posHeadEnd = stripos($content, '>', $posHeadStart);
                if ($posHeadEnd !== false) {
                    $content = substr($content, 0, $posHeadEnd + 1).PHP_EOL.$style.PHP_EOL.substr($content, $posHeadEnd + 1);
                } else {
                    $content = $style.PHP_EOL.$content;
                }
            } else {
                // 2. 如果没有找到任何 head 标签，在文档开头插入
                $content = $style.PHP_EOL.$content;
            }
        }

        // 尝试找到 </body> 标签的位置（不区分大小写）
        $posJs = strripos($content, '</body>');
        $posBodyCase = strripos($content, '</BODY>');

        // 使用找到的位置（区分大小写优先）
        $insertJsPos = max($posJs, $posBodyCase);

        if ($insertJsPos !== false) {
            $content = substr($content, 0, $insertJsPos).PHP_EOL.$traceContent.PHP_EOL.$script.substr($content, $insertJsPos);
        } else {
            // 如果没有找到 </body> 标签，尝试其他方案
            // 1. 尝试在 <body> 标签前插入
            $posBodyStart = stripos($content, '<body');
            if ($posBodyStart !== false) {
                $posBodyEnd = stripos($content, '>', $posBodyStart);
                if ($posBodyEnd !== false) {
                    $content = substr($content, 0, $posBodyEnd + 1).PHP_EOL.$traceContent.PHP_EOL.$script.PHP_EOL.substr($content, $posBodyEnd + 1);
                } else {
                    $content = $content.PHP_EOL.$traceContent.PHP_EOL.$script;
                }
            } else {
                // 2. 如果没有找到任何 body 标签，在文档末尾插入
                $content = $content.PHP_EOL.$traceContent.PHP_EOL.$script;
            }
        }

        $response->setContent($content);
        $response->headers->remove('Content-Length');

        if ($original = $response->getOriginalContent()) {
            $response->original = $original;
        }

        return $response;
    }
}
