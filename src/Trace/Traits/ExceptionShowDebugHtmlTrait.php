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
        // 死循环防护
        if (!\zxf\Trace\InfiniteLoopGuard::enter('outputDebugHtml')) {
            return response('系统错误', 500);
        }

        try {
            return $this->doOutputDebugHtml($list, $title, $statusCode, $showTrace);
        } finally {
            \zxf\Trace\InfiniteLoopGuard::exit();
        }
    }

    /**
     * 实际输出调试 HTML 页面
     *
     * @param  array  $list       调试信息列表（键值对）
     * @param  string  $title      页面标题
     * @param  int     $statusCode HTTP 状态码（默认 500）
     * @param  bool    $showTrace  是否显示 Trace 调试工具（默认 true）
     *
     * @return \Illuminate\Http\Response
     */
    private function doOutputDebugHtml(array $list = [], string $title = '', int $statusCode = 500, bool $showTrace = true)
    {
        // 处理标题，为空时使用默认标题
        $title = ! empty($title) ? $title : '系统错误/调试';

        // 标准化数据列表格式
        $newList = $this->normalizeDebugList($list);

        // 标题过长时截断
        $title = mb_strlen($title, 'utf-8') > 20 ? mb_substr($title, 0, 20, 'utf-8').'...' : $title;

        // 尝试使用 Blade 视图
        try {
            if (function_exists('view') && app()->bound('view')) {
                $view = app('view');
                
                // 检查 debug 视图是否存在
                if ($view->exists('trace::debug')) {
                    $html = $view->make('trace::debug', [
                        'title' => $title,
                        'list' => $newList,
                    ])->render();
                    
                    $resp = response($html, $statusCode)->header('Content-Type', 'text/html');
                    
                    // 如果不显示 Trace 调试工具，直接返回响应
                    if (! $showTrace) {
                        return $resp->send();
                    }
                    
                    // 否则，集成 Trace 调试工具
                    return $this->attachTraceToResponse($resp);
                }
            }
        } catch (\Throwable $e) {
            // 视图渲染失败，降级到内置模板
        }

        // 降级：使用 emergency 视图
        return $this->renderEmergencyView($title, $newList, $statusCode, $showTrace);
    }

    /**
     * 标准化调试数据列表
     *
     * @param array $list 原始数据列表
     * @return array 标准化后的列表
     */
    private function normalizeDebugList(array $list): array
    {
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
            // 已经是标准格式，处理 debug_file 类型
            foreach ($list as $item) {
                if ($item['type'] === 'debug_file') {
                    $item['editor'] = config('trace.editor') ?? 'phpstorm';
                }
                $newList[] = $item;
            }
        }
        
        return $newList;
    }

    /**
     * 附加 Trace 调试工具到响应
     *
     * @param \Illuminate\Http\Response $resp
     * @return \Illuminate\Http\Response
     */
    private function attachTraceToResponse($resp)
    {
        try {
            /** @var Handle $trace */
            $trace = app('trace');
            $request = app(Request::class);
            return $trace->renderTraceStyleAndScript($request, $resp)->send();
        } catch (\Throwable $e) {
            return $resp->send();
        }
    }

    /**
     * 渲染紧急视图（当 Blade 视图不可用时）
     *
     * @param string $title
     * @param array $list
     * @param int $statusCode
     * @param bool $showTrace
     * @return \Illuminate\Http\Response
     */
    private function renderEmergencyView(string $title, array $list, int $statusCode, bool $showTrace)
    {
        // 尝试使用 emergency 视图
        try {
            if (function_exists('view') && app()->bound('view')) {
                $view = app('view');
                if ($view->exists('trace::emergency')) {
                    $html = $view->make('trace::emergency', [
                        'code' => $statusCode,
                        'title' => $title,
                        'message' => '系统调试信息已收集，请查看详细内容',
                        'showDebug' => true,
                        'requestId' => $this->generateRequestId(),
                        'timestamp' => date('Y-m-d H:i:s'),
                    ])->render();
                    
                    $resp = response($html, $statusCode)->header('Content-Type', 'text/html');
                    
                    if (! $showTrace) {
                        return $resp->send();
                    }
                    
                    return $this->attachTraceToResponse($resp);
                }
            }
        } catch (\Throwable $e) {
            // 降级到最简响应
        }
        
        return response('系统错误', $statusCode);
    }

    /**
     * 生成请求 ID
     *
     * @return string
     */
    private function generateRequestId(): string
    {
        try {
            if (function_exists('request') && request()) {
                return request()->header('X-Request-ID', substr(md5(uniqid('', true)), 0, 12));
            }
        } catch (\Throwable $e) {
            // 忽略请求获取错误
        }
        return substr(md5(uniqid('', true)), 0, 12);
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
