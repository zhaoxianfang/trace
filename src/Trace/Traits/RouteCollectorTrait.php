<?php

namespace zxf\Trace\Traits;

use Closure;
use ReflectionFunction;
use ReflectionMethod;
use zxf\Trace\RouteFileAnalyzer;

/**
 * 路由信息收集 Trait
 *
 * 负责：
 * 1. 获取当前请求的路由信息（URI、控制器、中间件等）
 * 2. 解析控制器文件位置（用于 IDE 跳转）
 * 3. 解析路由定义文件位置（web.php 等路由文件，用于 IDE 跳转）
 */
trait RouteCollectorTrait
{
    /**
     * 获取路由信息
     *
     * @param  bool  $hasParseError  是否包含语法错误信息
     * @return array
     *
     * @throws \ReflectionException
     */
    private function getRouteInfo(bool $hasParseError): array
    {
        $route = $this->router->current();
        if (! $route instanceof \Illuminate\Routing\Route) {
            return [];
        }

        $uri = head($route->methods()) . ' ' . $route->uri();
        $action = $route->getAction();
        $result = [
            'uri' => $uri ?: '-',
        ];
        $result = array_merge($result, $action);
        $controller = is_string($action['controller'] ?? null) ? $action['controller'] : '';
        $uses = $action['uses'] ?? null;

        if (! $hasParseError) {
            // 语法错误无法执行这个代码段
            if (str_contains($controller, '@')) {
                [$ctrlClass, $method] = explode('@', $controller);
                $reflector = $this->getReflectionMethod($ctrlClass, $method);
                if ($reflector) {
                    $displayText = $this->getFilePath($reflector->getFileName()) . '#' . $reflector->getStartLine() . '-' . $reflector->getEndLine();
                    $result['file'] = [
                        'type' => 'file_link',
                        'label_override' => 'Controller File',
                        'label_class' => 'label-controller-file',
                        'file_path' => $reflector->getFileName(),
                        'line' => $reflector->getStartLine(),
                        'display' => $displayText,
                    ];

                    // 新增：解析路由定义文件位置
                    $routeDef = $this->resolveRouteDefinitionFile($ctrlClass, $method, $route->uri(), head($route->methods()));
                    if ($routeDef !== null) {
                        $routeDisplayText = $this->getFilePath($routeDef['file']) . '#' . $routeDef['start_line'] . '-' . $routeDef['end_line'];
                        $result['route_file'] = [
                            'type' => 'file_link',
                            'label_override' => 'Route File',
                            'label_class' => 'label-route-file',
                            'file_path' => $routeDef['file'],
                            'line' => $routeDef['start_line'],
                            'display' => $routeDisplayText,
                        ];
                    }
                }
                unset($result['uses']);
            } elseif ($uses instanceof Closure) {
                $reflector = new ReflectionFunction($uses);
                $displayText = $this->getFilePath($reflector->getFileName()) . '#' . $reflector->getStartLine() . '-' . $reflector->getEndLine();
                $result['file'] = [
                    'type' => 'file_link',
                    'label_override' => 'Controller File',
                    'label_class' => 'label-controller-file',
                    'file_path' => $reflector->getFileName(),
                    'line' => $reflector->getStartLine(),
                    'display' => $displayText,
                ];

                // 对于闭包路由，闭包所在的文件就是路由定义文件
                $result['route_file'] = [
                    'type' => 'file_link',
                    'label_override' => 'Route File',
                    'label_class' => 'label-route-file',
                    'file_path' => $reflector->getFileName(),
                    'line' => $reflector->getStartLine(),
                    'display' => $displayText,
                ];

                $result['uses'] = $uses;
            } elseif (is_string($uses) && str_contains($uses, '@__invoke')) {
                if (class_exists($controller) && method_exists($controller, 'render')) {
                    $reflector = $this->getReflectionMethod($controller, 'render');
                    if ($reflector) {
                        $displayText = $this->getFilePath($reflector->getFileName()) . '#' . $reflector->getStartLine() . '-' . $reflector->getEndLine();
                        $result['file'] = [
                            'type' => 'file_link',
                            'label_override' => 'Controller File',
                            'label_class' => 'label-controller-file',
                            'file_path' => $reflector->getFileName(),
                            'line' => $reflector->getStartLine(),
                            'display' => $displayText,
                        ];

                        // 新增：解析路由定义文件位置
                        $routeDef = $this->resolveRouteDefinitionFile($controller, 'render', $route->uri(), head($route->methods()));
                        if ($routeDef !== null) {
                            $routeDisplayText = $this->getFilePath($routeDef['file']) . '#' . $routeDef['start_line'] . '-' . $routeDef['end_line'];
                            $result['route_file'] = [
                                'type' => 'file_link',
                                'label_override' => 'Route File',
                                'label_class' => 'label-route-file',
                                'file_path' => $routeDef['file'],
                                'line' => $routeDef['start_line'],
                                'display' => $routeDisplayText,
                            ];
                        }

                        $result['controller'] = $controller . '@render';
                    }
                }
            }
        } else {
            // 截取$controller 字符串里 @ 符号前面的字符串
            $result['controller'] = substr($controller, 0, strrpos($controller, '@'));
            unset($result['uses']);
        }

        $parametersObj = $route->parameters();
        $parameters = [];
        foreach ($parametersObj as $param) {
            if (is_object($param)) {
                if (method_exists($param, 'getRouteKey')) {
                    $parameters[] = get_class($param) . ':[' . $param->getRouteKeyName() . ':' . $param->getRouteKey() . ']';
                } else {
                    $parameters[] = collect($param)->toArray();
                }
            } else {
                $parameters[] = $param;
            }
        }
        if ($parameters) {
            $result['params'] = $parameters;
        }

        $result['middleware'] = implode(', ', $route->middleware());
        $result['action'] = $route->getActionMethod();

        return $result;
    }

    /**
     * 解析路由定义文件位置
     *
     * 通过分析路由文件，定位当前请求路由的定义位置（web.php 等文件中的行号）
     *
     * @param string $controllerClass 控制器完整类名
     * @param string $method 方法名
     * @param string $compiledUri 编译后的路由 URI
     * @param string $httpMethod HTTP 方法
     * @return array{file: string, start_line: int, end_line: int}|null
     */
    private function resolveRouteDefinitionFile(
        string $controllerClass,
        string $method,
        string $compiledUri = '',
        string $httpMethod = 'GET'
    ): ?array {
        try {
            $analyzer = new RouteFileAnalyzer();
            return $analyzer->findRouteDefinition($controllerClass, $method, $compiledUri, $httpMethod);
        } catch (\Throwable $e) {
            // 路由文件分析失败不中断主流程
            if (config('app.debug', false)) {
                error_log('[Trace] Route definition file resolution error: ' . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * 获取反射方法
     *
     * 注意：不使用缓存，每次直接创建反射对象，避免内存累积
     *
     * @param  string  $class 类名
     * @param  string  $method 方法名
     * @return \ReflectionMethod|null
     */
    private function getReflectionMethod(string $class, string $method): ?\ReflectionMethod
    {
        try {
            return class_exists($class) && method_exists($class, $method)
                ? new ReflectionMethod($class, $method)
                : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
