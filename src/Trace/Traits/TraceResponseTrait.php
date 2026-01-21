<?php

namespace zxf\Trace\Traits;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * 把trace调试数据渲染到响应的html中
 */
trait TraceResponseTrait
{
    // 返回在页面只渲染调试页面
    public function randerPage($trace): string
    {
        $html = <<<'EOT'
    <div id="trace-tools-box">
    <div class="trace-logo">
      <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAYAAACNiR0NAAAAAXNSR0IArs4c6QAAAcBJREFUOE/F1MtKAlEYB/DvaI1OlJIuhBYKFbSzi9lKcIJadnkIoQeoTYvw0mu0laBlPYAOQUG2cFGEQdioQXbTUcsy9cQZm+HkXBRcdEAGjt/8zn/OzHcQ9DEwH4qQMhQ8kK5GAxn9iRMhDsw4DBi4Th2K9kI1QTXUvaw+rAI7j4fDvR5NL7ECSqlMOKEJ2WcAxIz2GgiSgBEvb4UE5g8DHGsdizgdE8Huu7B3B4CAZOROAAnHf0rE50pauCnA7N75vLTLMohwJ52VtfEExp51APeadqpfOHuV40vFshTCF0tJlgokkzb/dnF0atOlt49NsdDIHu3mag+303KNIch6t8BsnwTLECNaXIt2+aZW6b7+ep2sm0fGHU9ncfh8EZQ1+wLlaqb9VawIwjCB5LmBwGb1sY4/zCy9Bf8LMp5VYNwrSqCBExKJvBQCkysNiplTIL/uYfhS6GICmpxLb9W7rEMLkr49DMmF/dSy8h3KQD4eiCCk7uNGrZ0uFZpzOr0X9cUulGNNdTiQNoQ2cDSsBdKp6IV0z0M6LQ0SqGXCUX/0MqmV2PCAlfo8Hoh8v7c2yvlm2QiS8Z6gXj/rzf8AmFQQJJO/2LAAAAAASUVORK5CYII=" alt="Logo" style="height: 18px;" class="logo">
      <span class="title">Trace</span>
    </div>
    <div class="tabs-container">
      <div class="tabs-header">
        <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAYAAACNiR0NAAAAAXNSR0IArs4c6QAAAcBJREFUOE/F1MtKAlEYB/DvaI1OlJIuhBYKFbSzi9lKcIJadnkIoQeoTYvw0mu0laBlPYAOQUG2cFGEQdioQXbTUcsy9cQZm+HkXBRcdEAGjt/8zn/OzHcQ9DEwH4qQMhQ8kK5GAxn9iRMhDsw4DBi4Th2K9kI1QTXUvaw+rAI7j4fDvR5NL7ECSqlMOKEJ2WcAxIz2GgiSgBEvb4UE5g8DHGsdizgdE8Huu7B3B4CAZOROAAnHf0rE50pauCnA7N75vLTLMohwJ52VtfEExp51APeadqpfOHuV40vFshTCF0tJlgokkzb/dnF0atOlt49NsdDIHu3mag+303KNIch6t8BsnwTLECNaXIt2+aZW6b7+ep2sm0fGHU9ncfh8EZQ1+wLlaqb9VawIwjCB5LmBwGb1sY4/zCy9Bf8LMp5VYNwrSqCBExKJvBQCkysNiplTIL/uYfhS6GICmpxLb9W7rEMLkr49DMmF/dSy8h3KQD4eiCCk7uNGrZ0uFZpzOr0X9cUulGNNdTiQNoQ2cDSsBdKp6IV0z0M6LQ0SqGXCUX/0MqmV2PCAlfo8Hoh8v7c2yvlm2QiS8Z6gXj/rzf8AmFQQJJO/2LAAAAAASUVORK5CYII=" alt="Logo" class="tabs-logo-small">
        <div class="tabs-menu">
EOT;

        $tabNames = array_keys($trace);
        // tab name
        foreach ($tabNames as $key => $name) {
            $tabKey = ($key + 1);
            $html .= "<div class='tabs-item ".($key < 1 ? 'active' : '')."' data-tab='tab".$tabKey."'>".$name.'</div>';
        }

        $html .= <<<'EOT'
        </div>
        <div class="tabs-close">关闭</div>
      </div>
EOT;

        $tabIndex = 0;
        // tab content
        foreach ($trace as $key => $tabs) {
            $tabKey = ($tabIndex + 1);
            $tabIndex++;
            $active = ($tabIndex < 2 ? 'active' : '');
            $html .= <<<EOT
        <div id="tab{$tabKey}" class="tabs-content {$active}">
<ul>
EOT;
            foreach ($tabs as $k => $item) {
                $html .= '<li>';
                try {
                    if (is_array($item) && ! empty($item['type']) && $item['type'] == 'trace') {
                        // trace 数据跟踪信息打印
                        $html .= $this->handleTraceData($item);
                    }else{
                        // 左侧label
                        if(is_array($item) && ! empty($item['label'])){
                            $html .= "<span class='json-label'>{$item['label']}</span>";
                        }elseif (is_string($k)){
                            $html .= "<span class='json-label'>{$k}</span>";
                        }

                        // 中间 对象/数组/字符串
                        if (!is_array($item)) {
                            $class = is_numeric($k) ? 'json-label' : 'json-string-content';
                            // 是标量 或者空
                            if (is_scalar($item) || is_null($item)) {
                                $html .= "<div class='{$class}'>".format_param($item).'</div>';
                            } else {
                                $html .= "<div class='{$class}'>".(ucfirst(gettype($item)).':'.get_class($item)).'</div>';
                            }
                        }else{
                            if(!empty(array_diff(array_keys($item), ['label', 'right']))){
                                // $arrayString = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                                $arrayString = json_encode($item, JSON_UNESCAPED_UNICODE);
                                $html .= <<<EOT
    <div class="json-arrow-pre-wrapper">
      <span class="json-arrow" onclick="toggleJson(this)">▶</span>
      <pre class="json">{$arrayString}</pre>
    </div>
EOT;
                            }elseif (empty($item)){
                                $html .= "<span class='json-string-content'>array[]</span>";
                            }
                        }

                        // 右侧right
                        if (is_array($item) && ! empty($item['right'])) {
                            $html .= "<span class='json-right'>".$item['right'].'</span>';
                        }
                    }
                } catch (Exception $e) {
                    $html .= "<div class='json-string-content'> Unrecognized data </div>";
                }
                $html .= '</li>';
            }

            $html .= <<<'EOT'
        </ul>
       </div>
EOT;
        }

        $html .= <<<'EOT'
      </div></div>
EOT;

        return $html;
    }

    protected function handleTraceData($data = []): string
    {
        $editor = config('trace.editor') ?? 'phpstorm';
        $str = '<span class="json-label"><a href="'.$editor.'://open?file='.urlencode($data['file_path']).'&amp;line='.$data['line'].'" class="phpdebugbar-link">'.$data['local'].'</a></span>';

        if (is_array($data['var']) && ! empty($data['var'])) {
            $arrayString = json_encode($data['var'], JSON_UNESCAPED_UNICODE);
            $str .= <<<EOT
                    <div class="json-arrow-pre-wrapper">
                      <span class="json-arrow" onclick="toggleJson(this)">▶</span>
                      <pre class="json">{$arrayString}</pre>
                    </div>
EOT;
        } else {
            if(is_array($data['var'])){
                $str .= "<div class='json-string-content'>[]</div>";
            }else{
                // 是标量 或者空
                if (is_scalar($data['var']) || is_null($data['var'])) {
                    $str .= "<div class='json-string-content'>".format_param($data['var']).'</div>';
                } else {
                    $str .= "<div class='json-string-content'>".($data['var']).'</div>';
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
