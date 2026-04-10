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
        $fileName = $displayText ?? str_replace(base_path(), '', $file);

        return '<span class="json-label"><a href="'.$this->escapeHtml($editor).'://open?file='.urlencode($file).'&amp;line='.$line.'" class="phpdebugbar-link">'.($fileName.'#'.$line).'</a></span>';
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
     * @param array $trace 跟踪数据
     * @return string HTML内容
     */
    protected function renderBladePanel(array $trace): string
    {
        $view = view('trace::trace-panel', ['trace' => $trace]);

        return $view->render();
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

            foreach ($tabs as $k => $item) {
                $itemHtml = $this->renderDynamicPanelItem($k, $item, $editor);
                $contentItems .= "<li>{$itemHtml}</li>";
            }

            $tabContents .= "<div id=\"tab{$tabKey}\" class=\"tabs-content {$active}\" role=\"tabpanel\" aria-labelledby=\"tab{$tabKey}\"><ul>{$contentItems}</ul></div>";
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

        // 带有 HTML 提示的空状态
        if (is_array($item) && isset($item['has_html']) && $item['has_html']) {
            $message = $this->escapeHtml($item['message'] ?? '');
            $tips = $this->escapeHtml($item['tips'] ?? '');
            return "<li><span class='json-label'>{$message}</span><span class='json-string-content' style=\"font-size: 12px; color: #aaa;\">提示: {$tips}</span></li>";
        }

        // 带有原始 HTML 的内容（如异常文件链接）
        if (is_array($item) && isset($item['raw_html']) && $item['raw_html']) {
            $content = $item['content'];
            // 如果包含错误行代码高亮，添加样式
            if (is_string($content) && str_contains($content, 'error-line-code')) {
                return "<li><pre style='background:#1e1e2e;color:#cdd6f4;padding:15px;border-radius:6px;overflow-x:auto;font-family:Consolas,Monaco,Courier New,monospace;font-size:13px;line-height:1.6;margin:5px 0;'>{$content}</pre></li>";
            }
            return "<li>{$content}</li>";
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
            $diffKeys = array_diff(array_keys($item), ['label', 'right', 'has_html', 'message', 'tips', 'raw_html', 'content']);
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
        $filePath = $this->escapeHtml($data['file_path'] ?? '');
        $line = (int) ($data['line'] ?? 1);
        $local = $this->escapeHtml($data['local'] ?? '');
        $var = $data['var'] ?? null;

        $safeEditor = $this->escapeHtml($editor);
        $html = '<span class="json-label">';
        $html .= '<a href="' . $safeEditor . '://open?file=' . urlencode($filePath) . '&amp;line=' . $line . '" class="phpdebugbar-link">' . $local . '</a>';
        $html .= '</span>';

        if (is_array($var) && !empty($var)) {
            $jsonString = $this->escapeHtml(json_encode($var, JSON_UNESCAPED_UNICODE));
            $html .= <<<JSON
<div class="json-arrow-pre-wrapper">
    <span class="json-arrow" onclick="toggleJson(this)">▶</span>
    <pre class="json">{$jsonString}</pre>
</div>
JSON;
        } elseif (is_array($var)) {
            $html .= "<div class='json-string-content'>[]</div>";
        } else {
            if (is_scalar($var) || is_null($var)) {
                $value = $this->escapeHtml(format_param($var));
                $html .= "<div class='json-string-content'>{$value}</div>";
            } else {
                $value = $this->escapeHtml((string) $var);
                $html .= "<div class='json-string-content'>{$value}</div>";
            }
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
