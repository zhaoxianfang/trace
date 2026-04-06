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

    // 返回在页面只渲染调试页面
    public function randerPage($trace): string
    {
        // 使用输出缓冲提高性能
        ob_start();

        // 获取缓存的编辑器配置
        if (self::$editorConfig === null) {
            self::$editorConfig = config('trace.editor') ?? 'phpstorm';
        }

        ?>
    <div id="trace-tools-box">
    <div class="trace-logo">
      <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAYAAACNiR0NAAAAAXNSR0IArs4c6QAAAcBJREFUOE/F1MtKAlEYB/DvaI1OlJIuhBYKFbSzi9lKcIJadnkIoQeoTYvw0mu0laBlPYAOQUG2cFGEQdioQXbTUcsy9cQZm+HkXBRcdEAGjt/8zn/OzHcQ9DEwH4qQMhQ8kK5GAxn9iRMhDsw4DBi4Th2K9kI1QTXUvaw+rAI7j4fDvR5NL7ECSqlMOKEJ2WcAxIz2GgiSgBEvb4UE5g8DHGsdizgdE8Huu7B3B4CAZOROAAnHf0rE50pauCnA7N75vLTLMohwJ52VtfEExp51APeadqpfOHuV40vFshTCF0tJlgokkzb/dnF0atOlt49NsdDIHu3mag+303KNIch6t8BsnwTLECNaXIt2+aZW6b7+ep2sm0fGHU9ncfh8EZQ1+wLlaqb9VawIwjCB5LmBwGb1sY4/zCy9Bf8LMp5VYNwrSqCBExKJvBQCkysNiplTIL/uYfhS6GICmpxLb9W7rEMLkr49DMmF/dSy8h3KQD4eiCCk7uNGrZ0uFZpzOr0X9cUulGNNdTiQNoQ2cDSsBdKp6IV0z0M6LQ0SqGXCUX/0MqmV2PCAlfo8Hoh8v7c2yvlm2QiS8Z6gXj/rzf8AmFQQJJO/2LAAAAAASUVORK5CYII=" alt="Logo" style="height: 18px;" class="logo">
      <span class="title">Trace</span>
    </div>
    <div class="tabs-container">
      <div class="tabs-header">
        <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAYAAACNiR0NAAAAAXNSR0IArs4c6QAAAcBJREFUOE/F1MtKAlEYB/DvaI1OlJIuhBYKFbSzi9lKcIJadnkIoQeoTYvw0mu0laBlPYAOQUG2cFGEQdioQXbTUcsy9cQZm+HkXBRcdEAGjt/8zn/OzHcQ9DEwH4qQMhQ8kK5GAxn9iRMhDsw4DBi4Th2K9kI1QTXUvaw+rAI7j4fDvR5NL7ECSqlMOKEJ2WcAxIz2GgiSgBEvb4UE5g8DHGsdizgdE8Huu7B3B4CAZOROAAnHf0rE50pauCnA7N75vLTLMohwJ52VtfEExp51APeadqpfOHuV40vFshTCF0tJlgokkzb/dnF0atOlt49NsdDIHu3mag+303KNIch6t8BsnwTLECNaXIt2+aZW6b7+ep2sm0fGHU9ncfh8EZQ1+wLlaqb9VawIwjCB5LmBwGb1sY4/zCy9Bf8LMp5VYNwrSqCBExKJvBQCkysNiplTIL/uYfhS6GICmpxLb9W7rEMLkr49DMmF/dSy8h3KQD4eiCCk7uNGrZ0uFZpzOr0X9cUulGNNdTiQNoQ2cDSsBdKp6IV0z0M6LQ0SqGXCUX/0MqmV2PCAlfo8Hoh8v7c2yvlm2QiS8Z6gXj/rzf8AmFQQJJO/2LAAAAAASUVORK5CYII=" alt="Logo" class="tabs-logo-small">
        <div class="tabs-menu">
<?php
        $tabNames = array_keys($trace);
        // tab name
        foreach ($tabNames as $key => $name):
            $tabKey = ($key + 1);
            $activeClass = ($key < 1) ? 'active' : '';
            $isSelected = $key < 1 ? 'true' : 'false';
?>
          <div class='tabs-item <?php echo $activeClass; ?>' data-tab='tab<?php echo $tabKey; ?>' tabindex='0' role='tab' aria-selected='<?php echo $isSelected; ?>'><?php echo htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></div>
<?php endforeach; ?>
        </div>
        <div class="tabs-close" title="关闭调试面板 (ESC)">×</div>
      </div>
<?php

        $tabIndex = 0;
        // tab content
        foreach ($trace as $key => $tabs):
            $tabKey = ++$tabIndex;
            $active = ($tabIndex < 2 ? 'active' : '');
?>
        <div id="tab<?php echo $tabKey; ?>" class="tabs-content <?php echo $active; ?>" role="tabpanel" aria-labelledby="tab<?php echo $tabKey; ?>">
<ul>
<?php
            foreach ($tabs as $k => $item):
                // 处理SQL分组
                if (is_array($item) && isset($item['type']) && $item['type'] == 'sql_group'):
                    echo $this->renderSqlGroup($item);
                    continue;
                endif;
?>
          <li>
<?php
                try {
                    if (is_array($item) && ! empty($item['type']) && $item['type'] == 'trace'):
                        // trace 数据跟踪信息打印
                        echo $this->handleTraceData($item);
                    else:
                        // 左侧label
                        if(is_array($item) && ! empty($item['label'])):
?>
            <span class='json-label'><?php echo $this->escapeHtml($item['label']); ?></span>
<?php
                        elseif (is_string($k)):
?>
            <span class='json-label'><?php echo $this->escapeHtml($k); ?></span>
<?php
                        endif;

                        // 中间 对象/数组/字符串
                        if (!is_array($item)):
                            $class = is_numeric($k) ? 'json-label' : 'json-string-content';
                            // 是标量 或者空
                            if (is_scalar($item) || is_null($item)):
?>
            <div class='<?php echo $class; ?>'><?php echo format_param($item); ?></div>
<?php
                            else:
?>
            <div class='<?php echo $class; ?>'><?php echo ucfirst(gettype($item)).':'.get_class($item); ?></div>
<?php
                            endif;
                        else:
                            if(!empty(array_diff(array_keys($item), ['label', 'right']))):
                                $arrayString = json_encode($item, JSON_UNESCAPED_UNICODE);
?>
    <div class="json-arrow-pre-wrapper">
      <span class="json-arrow" onclick="toggleJson(this)" role="button" tabindex="0" aria-expanded="false">▶</span>
      <pre class="json"><?php echo $arrayString; ?></pre>
    </div>
<?php
                            elseif (empty($item)):
?>
            <span class='json-string-content'>[]</span>
<?php
                            endif;
                        endif;

                        // 右侧right
                        if (is_array($item) && ! empty($item['right'])):
?>
            <span class='json-right'><?php echo $this->escapeHtml((string)$item['right']); ?></span>
<?php
                        endif;
                    endif;
                } catch (Exception $e) {
?>
            <div class='json-string-content' style='color: #ef4444;'>⚠️ 数据解析错误</div>
<?php
                }
?>
          </li>
<?php endforeach; ?>
        </ul>
       </div>
<?php endforeach; ?>
      </div></div>
<?php
        // 获取输出缓冲内容并清理
        return ob_get_clean();
    }

    /**
     * 渲染SQL分组
     *
     * @param  array  $group 分组数据
     * @return string HTML字符串
     */
    protected function renderSqlGroup(array $group): string
    {
        $groupName = $this->escapeHtml($group['name'] ?? '');
        $groupClass = $this->escapeHtml($group['class'] ?? 'sql-group');
        $sqlCount = (int) ($group['count'] ?? 0);
        $collapsed = ($group['collapsed'] ?? false) ? 'collapsed' : '';

        $html = <<<EOT
<div class="sql-group {$groupClass} {$collapsed}">
  <div class="sql-group-header">
    <div class="sql-group-title">
      <span>{$groupName}</span>
      <span class="sql-group-count">{$sqlCount}条</span>
    </div>
    <span class="sql-group-toggle">▼</span>
  </div>
  <ul class="sql-group-content">
EOT;

        $sqls = is_array($group['sqls'] ?? null) ? $group['sqls'] : [];
        foreach ($sqls as $sqlItem) {
            $sql = $this->escapeHtml($sqlItem['label'] ?? '');
            $time = $this->escapeHtml($sqlItem['right'] ?? '-');

            $html .= <<<EOT
    <li>
      <span class="json-label">{$sql}</span>
      <span class="json-right">{$time}</span>
    </li>
EOT;
        }

        $html .= <<<EOT
  </ul>
</div>
EOT;

        return $html;
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

    protected function handleTraceData($data = []): string
    {
        // 使用缓存的编辑器配置
        if (self::$editorConfig === null) {
            self::$editorConfig = config('trace.editor') ?? 'phpstorm';
        }
        $editor = self::$editorConfig;
        $filePath = $this->escapeHtml($data['file_path'] ?? '');
        $line = (int) ($data['line'] ?? 1);
        $local = $this->escapeHtml($data['local'] ?? '');
        $var = $data['var'] ?? null;

        $str = '<span class="json-label"><a href="'.$this->escapeHtml($editor).'://open?file='.urlencode($filePath).'&amp;line='.$line.'" class="phpdebugbar-link">'.$local.'</a></span>';

        if (is_array($var) && ! empty($var)) {
            $arrayString = $this->escapeHtml(json_encode($var, JSON_UNESCAPED_UNICODE));
            $str .= <<<EOT
                    <div class="json-arrow-pre-wrapper">
                      <span class="json-arrow" onclick="toggleJson(this)">▶</span>
                      <pre class="json">{$arrayString}</pre>
                    </div>
EOT;
        } else {
            if (is_array($var)) {
                $str .= "<div class='json-string-content'>[]</div>";
            } else {
                // 是标量 或者空
                if (is_scalar($var) || is_null($var)) {
                    $str .= "<div class='json-string-content'>".$this->escapeHtml(format_param($var)).'</div>';
                } else {
                    $str .= "<div class='json-string-content'>".$this->escapeHtml((string) $var).'</div>';
                }
            }
        }

        return $str;
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
