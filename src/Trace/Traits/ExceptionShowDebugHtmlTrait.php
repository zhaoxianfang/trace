<?php

namespace zxf\Trace\Traits;

use Illuminate\Http\Request;
use zxf\Trace\Handle;

/**
 * 异常调试 HTML 输出 Trait
 *
 * 负责：
 * 1. 生成友好的异常调试页面
 * 2. 支持多种数据类型展示（字符串、代码、文件链接）
 * 3. 响应式布局，适配移动端
 * 4. 可选集成 Trace 调试工具
 */
trait ExceptionShowDebugHtmlTrait
{
    /**
     * 输出调试 HTML 页面
     *
     * @param  array  $list       调试信息列表（键值对）
     * @param  string  $title      页面标题
     * @param  int     $statusCode HTTP 状态码（默认 500）
     * @param  bool    $showTrace  是否显示 Trace 调试工具（默认 true）
     *
     * @return \Illuminate\Http\Response
     */
    public function outputDebugHtml(array $list = [], string $title = '', int $statusCode = 500, bool $showTrace = true)
    {
        // 处理标题，为空时使用默认标题
        $title = ! empty($title) ? $title : '系统错误/调试';

        // 标准化数据列表格式
        $newList = [];
        if (! $this->isValidMultiDimensionalArray($list)) {
            // 将数据转换为标准格式：['type', 'label', 'value']
            foreach ($list as $key => $value) {
                $type = is_array($value) ? 'code' : 'string';
                $newList[] = [
                    'type' => $type,
                    'label' => $key,
                    'value' => $type == 'code'
                        ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                        : $value,
                ];
            }
        } else {
            // 已经是标准格式，直接使用
            $newList = $list;
        }

        // 生成 HTML 内容
        $content = '';
        /**
         * @var array $row 数据项格式：['type'=> 'code|debug_file|string', 'label' => '标签', 'value' => '值']
         */
        foreach ($newList as $row) {
            // 处理代码类型（JSON、数组等）
            if ($row['type'] == 'code') {
                $content .= '<li class="info-item">
                    <span class="info-label">'.$row['label'].'：</span>
                    <div class="info-value"><pre><code>'.$row['value'].'</code></pre></div>
                </li>';
            }
            // 处理调试文件链接类型（可点击跳转到编辑器）
            elseif ($row['type'] == 'debug_file') {
                $editor = config('trace.editor') ?? 'phpstorm';
                $content .= '<li class="info-item">
                    <span class="info-label">'.$row['label'].'：</span>
                    <div class="info-value">'.'<a href="'.$editor.'://open?file='.urlencode($row['file']).'&amp;line='.$row['line'].'" class="phpdebugbar-link">'.($row['value']).'</a>'.'</div>
                </li>';
            }
            // 处理普通字符串类型
            else {
                $content .= '<li class="info-item">
                    <span class="info-label">'.$row['label'].'：</span>
                    <div class="info-value">'.(is_string($row['value']) ? $row['value'] : var_export($row['value'], true)).'</div>
                </li>';
            }
        }

        // 获取系统名称
        $sysName = config('app.name', '威四方');
        // 生成版权信息
        $copyright = '&copy; '.date('Y').' '.$sysName.' ('.config('app.url', 'https://weisifang.com').') 版权所有.';
        // 标题过长时截断
        $title = mb_strlen($title, 'utf-8') > 15 ? mb_substr($title, 0, 15, 'utf-8').'...' : $title;

        // 生成完整的 HTML 页面
        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}|{$sysName}</title>
    <style>
        /* 基础样式重置 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            width: 88%;
            min-height: calc(100vh - 50px);
            margin: 0 auto;
            /*background: #fff;*/
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;

        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            /*color: #2c3e50;*/
            color: red;
        }

        .info-list {
            list-style: none;
        }

        .info-item {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px dashed #7f8c8d;
            align-items: flex-start;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: bold;
            color: #3498db;
            min-width: 120px;
            padding-right: 20px;
        }

        .info-value {
            flex: 1;
            word-break: break-word;
            color: #fff;
            overflow: auto;
        }

        .info-value pre {
            color: #000;
            border-radius: 4px;
            padding: 12px;
            overflow-x: auto;
            font-family: 'Courier New', Courier, monospace;
            border-left: 3px solid #3498db;
            margin: 5px 0;
            tab-size: 4;
            background-color: #f8f8f8;
        }

        /* 响应式设计 - 移动端适配 */
        @media (max-width: 600px) {
            .info-item {
                flex-direction: column;
            }

            .info-label {
                margin-bottom: 5px;
            }
        }

        /* 页脚样式 */
        footer {
            text-align: center;
            padding: 12px;
            color: #7f8c8d;
            font-size: 14px;
            margin-top: 20px;
            position: static;
            width: 100%;
            bottom: 10px;
        }

        footer span{
            font-size: 10px;
            margin-left: 30px;
        }

        footer a{
            color: #3498db;
        }

        .phpdebugbar-link{
            color: #03dac6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>{$title}</h1>

        <ul class="info-list">
            {$content}
        </ul>
    </div>
    <footer>
        {$copyright}  <span> 本页面由 <a href="https://weisifang.com/" target="_blank">weisifang.com</a> 提供支持</span>
    </footer>
</body>
</html>
HTML;

        // 创建响应对象
        $resp = response($html, $statusCode)->header('Content-Type', 'text/html');

        // 如果不显示 Trace 调试工具，直接返回响应
        if (! $showTrace) {
            return $resp->send();
        }

        // 否则，集成 Trace 调试工具
        /** @var Handle $trace */
        $trace = app('trace');

        // 获取当前请求对象
        $request = app(Request::class);

        return $trace->renderTraceStyleAndScript($request, $resp)->send();
    }

    /**
     * 验证是否为有效的多维数组
     *
     * 有效的格式：每个元素必须是数组且包含 'type'、'label'、'value' 键
     *
     * @param  array  $array 待验证的数组
     *
     * @return bool true 表示有效，false 表示无效
     */
    private function isValidMultiDimensionalArray(array $array): bool
    {
        foreach ($array as $item) {
            if (! is_array($item) || ! isset($item['type'], $item['label'], $item['value'])) {
                return false;
            }
        }

        // 数组不能为空
        return ! empty($array);
    }
}
