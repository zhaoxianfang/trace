<?php

namespace zxf\Trace\Traits;

/**
 * 视图数据收集 Trait
 *
 * 负责：
 * 1. 监听视图编译事件，收集视图加载数据和传递的参数
 * 2. 获取已加载的视图信息列表
 * 3. 支持视图文件路径 IDE 链接跳转
 */
trait ViewCollectorTrait
{
    /**
     * 监听视图编译事件，收集视图加载数据和传递的参数
     *
     * 捕获每个已加载视图的路径和传递的所有参数/变量，
     * 用于在 View 标签页中展开查看
     *
     * @return void
     */
    protected function listenViewComposing(): void
    {
        try {
            if (!class_exists('Illuminate\Support\Facades\Event')) {
                return;
            }

            \Illuminate\Support\Facades\Event::listen('composing:*', function ($viewName, $payload = []) {
                if (self::$currentRequestId !== $this->requestId) {
                    return;
                }

                try {
                    $view = $payload[0] ?? null;
                    if (!$view instanceof \Illuminate\View\View) {
                        return;
                    }

                    $viewName = $view->getName();
                    $viewPath = $view->getPath();

                    // 忽略 trace 包自身的视图
                    if (str_contains($viewPath, 'zxf/trace') || str_contains($viewPath, 'zxf\trace')) {
                        return;
                    }

                    $viewData = $view->getData();
                    $filteredData = array_diff_key($viewData, array_flip([
                        '__env', 'app', 'errors',
                    ]));

                    $requestId = $this->requestId;

                    self::$viewDataCollector[$requestId] ??= [];
                    self::$viewDataCollector[$requestId][$viewName] = [
                        'name' => $viewName,
                        'path' => $viewPath,
                        'data' => $filteredData,
                    ];
                } catch (\Throwable $e) {
                    // 静默处理视图数据收集错误
                }
            });
        } catch (\Throwable $e) {
            // 静默处理监听器注册错误
        }
    }

    /**
     * 获取已加载的视图信息
     *
     * @return array 视图信息列表
     */
    public function getViewInfo(): array
    {
        $viewFiles = [];
        $viewData = self::$viewDataCollector[$this->requestId] ?? [];

        if (!empty($viewData)) {
            foreach ($viewData as $viewName => $info) {
                $relativePath = trim(str_replace(base_path() ?: '', '', $info['path']), '/');
                $paramCount = count($info['data']);

                $viewFiles[] = [
                    'type' => 'view_item',
                    'label' => $viewName,
                    'right' => $paramCount > 0 ? $paramCount . ' 个参数' : '',
                    'view_path' => $info['path'],
                    'view_path_rel' => $relativePath,
                    'view_params' => $info['data'],
                    'param_count' => $paramCount,
                ];

                if (count($viewFiles) >= 100) {
                    break;
                }
            }

            unset(self::$viewDataCollector[$this->requestId]);
            return $viewFiles;
        }

        // 回退方案：仅显示视图文件路径（view composer 不可用时）
        try {
            $viewFinder = app('view')->getFinder();
            $views = method_exists($viewFinder, 'getViews') ? $viewFinder->getViews() : [];
            foreach ($views as $alias => $viewPath) {
                $relativePath = trim(str_replace(base_path() ?: '', '', $viewPath), '/');
                $viewFiles[] = [
                    'type' => 'view_item',
                    'label' => $alias,
                    'right' => '',
                    'view_path' => $viewPath,
                    'view_path_rel' => $relativePath,
                    'view_params' => [],
                    'param_count' => 0,
                ];
                if (count($viewFiles) >= 100) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            // 视图查找器不可用
        }

        unset(self::$viewDataCollector[$this->requestId]);

        return $viewFiles;
    }
}
