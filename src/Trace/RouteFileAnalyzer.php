<?php

namespace zxf\Trace;

/**
 * 路由文件分析器
 *
 * 负责解析 PHP 路由文件，定位路由定义所在的文件和行号。
 * 用于在调试面板 Route 标签页中展示 IDE 可点击的路由文件链接。
 */
class RouteFileAnalyzer
{
    /**
     * 解析结果缓存（请求级别）
     *
     * @var array
     */
    private static array $cache = [];

    /**
     * 静态缓存最大条目数（用于常驻进程环境防止内存累积）
     */
    private const MAX_CACHE = 1000;

    /**
     * 获取路由定义所在的文件及行号
     *
     * @param string $controllerClass
     * @param string $method
     * @param string $compiledUri
     * @param string $httpMethod
     * @return array|null
     */
    public function findRouteDefinition(
        string $controllerClass,
        string $method,
        string $compiledUri = '',
        string $httpMethod = 'GET'
    ): ?array {
        $cacheKey = md5($controllerClass . '::' . $method . '::' . $compiledUri . '::' . $httpMethod);

        // 常驻进程（Octane/Swoole）环境下，静态缓存可能跨请求累积，超过上限即清空
        if (count(self::$cache) > self::MAX_CACHE) {
            self::$cache = [];
        }

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey] ?: null;
        }

        $result = null;

        try {
            $routeFiles = $this->discoverRouteFiles();

            // 策略1: 按控制器类名+方法名搜索
            $result = $this->searchByController($routeFiles, $controllerClass, $method);
            if ($result !== null) {
                self::$cache[$cacheKey] = $result;
                return $result;
            }

            // 策略2: 按编译 URI 搜索
            if ($compiledUri) {
                $result = $this->searchByUri($routeFiles, $compiledUri, $httpMethod);
                if ($result !== null) {
                    self::$cache[$cacheKey] = $result;
                    return $result;
                }
            }
        } catch (\Throwable $e) {
            if (config('app.debug', false)) {
                error_log('[Trace] Route file analysis error: ' . $e->getMessage());
            }
        }

        self::$cache[$cacheKey] = $result ?? [];
        return $result;
    }

    /**
     * 发现项目的所有路由文件
     *
     * @return string[]
     */
    public function discoverRouteFiles(): array
    {
        $cacheKey = '_route_files_discovered';
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $files = [];
        $basePath = base_path() ?: '';

        // 1. Laravel 标准路由目录
        $standardPaths = [
            $basePath . '/routes',
            $basePath . '/app/Routes',
        ];
        foreach ($standardPaths as $dir) {
            if (is_dir($dir)) {
                foreach (glob($dir . '/*.php') as $file) {
                    $files[] = realpath($file);
                }
            }
        }

        // 2. 模块路由目录
        $moduleBasePaths = [
            $basePath . '/Modules',
            $basePath . '/app/Modules',
            $basePath . '/modules',
        ];
        foreach ($moduleBasePaths as $modulePath) {
            if (!is_dir($modulePath)) {
                continue;
            }
            $moduleDirs = glob($modulePath . '/*', GLOB_ONLYDIR);
            if ($moduleDirs === false) {
                continue;
            }
            foreach ($moduleDirs as $moduleDir) {
                $routeDir = $moduleDir . '/Routes';
                if (is_dir($routeDir)) {
                    foreach (glob($routeDir . '/*.php') as $file) {
                        $files[] = realpath($file);
                    }
                }
            }
        }

        // 3. 配置中指定的额外路径
        $extraPaths = config('trace.route_paths', []);
        foreach ((array) $extraPaths as $path) {
            $absPath = str_starts_with($path, '/') ? $path : $basePath . '/' . ltrim($path, '/');
            if (is_dir($absPath)) {
                foreach (glob($absPath . '/*.php') as $file) {
                    $files[] = realpath($file);
                }
            } elseif (is_file($absPath) && str_ends_with($absPath, '.php')) {
                $files[] = realpath($absPath);
            }
        }

        $files = array_values(array_unique(array_filter($files)));

        self::$cache[$cacheKey] = $files;
        return $files;
    }

    /**
     * 通过控制器类名和方法名在路由文件中搜索
     *
     * 支持以下场景：
     * 1. 直接短类名 import（如 use App\Http\Controllers\BlogController; → BlogController::class）
     * 2. 命名空间前缀 import（如 use Modules\Blog\Http\Controllers\Web; → Web\BlogController::class）
     * 3. 完整类名在路由组闭包中引用
     * 4. 字符串形式的 controller@method
     *
     * @param string[] $routeFiles
     * @param string $controllerClass
     * @param string $method
     * @return array|null
     */
    private function searchByController(array $routeFiles, string $controllerClass, string $method): ?array
    {
        $shortName = substr(strrchr($controllerClass, '\\'), 1) ?: $controllerClass;
        if (empty($shortName)) {
            return null;
        }

        foreach ($routeFiles as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if ($lines === false) {
                continue;
            }

            $importMap = $this->buildImportMap($lines);

            $searchPatterns = [];

            // 策略A: 直接短类名 import（alias 就是类名本身）
            // 例: use App\Http\Controllers\BlogController; → BlogController::class
            if (isset($importMap[$shortName]) && $importMap[$shortName] === $controllerClass) {
                $searchPatterns[] = $shortName . '::class';
            }

            // 策略B: 命名空间前缀 import（alias 是命名空间前缀）
            // 例: use Modules\Blog\Http\Controllers\Web; → Web\BlogController::class
            // 需要在 import map 中找到能拼接出完整类名的 alias
            foreach ($importMap as $alias => $fullNamespace) {
                $resolvedClass = $fullNamespace . '\\' . $shortName;
                if ($resolvedClass === $controllerClass) {
                    $searchPatterns[] = $alias . '\\\\' . $shortName . '::class';
                    // 也尝试直接匹配不带 ::class 的形式（数组语法 [alias\ShortName::class, 'method']）
                    $searchPatterns[] = $alias . '\\\\' . $shortName;
                    break; // 找到一个匹配即可
                }
            }

            // 策略C: 完整类名出现在路由文件中（不依赖 import）
            $searchPatterns[] = preg_quote($controllerClass, '/');

            // 策略D: controller@method 字符串格式
            $searchPatterns[] = preg_quote($controllerClass . '@' . $method, '/');

            foreach ($lines as $lineIndex => $line) {
                $lineNumber = $lineIndex + 1;

                // 检查是否包含路由定义关键字
                if (!preg_match('/Route::(get|post|put|patch|delete|options|any|match|redirect|view|permanentRedirect)\s*\(/i', $line)) {
                    continue;
                }

                // 检查是否包含目标控制器引用
                $found = false;
                foreach ($searchPatterns as $pattern) {
                    if (preg_match('/' . $pattern . '/', $line)) {
                        $found = true;
                        break;
                    }
                }

                if ($found) {
                    // 确定结束行
                    $endLine = $this->findRouteEndLine($lines, $lineIndex);
                    return [
                        'file' => $file,
                        'start_line' => $lineNumber,
                        'end_line' => $endLine,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * 查找路由定义的结束行号
     *
     * @param string[] $lines
     * @param int $startIndex
     * @return int
     */
    private function findRouteEndLine(array $lines, int $startIndex): int
    {
        $lineNumber = $startIndex + 1;
        $count = count($lines);
        $maxLook = min($count, $startIndex + 15);

        for ($j = $startIndex; $j < $maxLook; $j++) {
            if (str_contains($lines[$j], ';')) {
                return $j + 1;
            }
        }

        return $lineNumber;
    }

    /**
     * 通过编译后 URI 在路由文件中搜索
     *
     * @param string[] $routeFiles
     * @param string $compiledUri
     * @param string $httpMethod
     * @return array|null
     */
    private function searchByUri(array $routeFiles, string $compiledUri, string $httpMethod): ?array
    {
        $searchUri = trim($compiledUri, '/');
        $hasParams = str_contains($compiledUri, '{');
        // 标记是否为根路由（/）
        $isRootRoute = ($compiledUri === '/' || empty($searchUri));

        foreach ($routeFiles as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if ($lines === false) {
                continue;
            }

            foreach ($lines as $lineIndex => $line) {
                $lineNumber = $lineIndex + 1;

                if (!preg_match('/Route::(get|post|put|patch|delete|options|any|match|redirect|view|permanentRedirect)\s*\(/i', $line, $m)) {
                    continue;
                }

                $methodInFile = strtoupper($m[1]);
                if ($methodInFile !== 'ANY' && $methodInFile !== 'MATCH' && strcasecmp($methodInFile, $httpMethod) !== 0) {
                    continue;
                }

                $matched = false;

                if ($isRootRoute) {
                    // 根路由匹配：Route::get('/' ... ) 或 Route::get("/" ... )
                    $matched = preg_match('/Route::\w+\s*\(\s*[\'"]\/[\'"]\s*[,)]/', $line) === 1;
                } elseif (!$hasParams && $searchUri) {
                    $matched = str_contains($line, $searchUri)
                        || str_contains($line, '/' . $searchUri)
                        || str_contains($line, '"' . $searchUri)
                        || str_contains($line, "'" . $searchUri);
                } elseif ($hasParams && $searchUri) {
                    $staticPrefix = explode('{', $searchUri)[0];
                    $matched = $staticPrefix && str_contains($line, $staticPrefix);
                }

                if ($matched) {
                    $endLine = $this->findRouteEndLine($lines, $lineIndex);
                    return [
                        'file' => $file,
                        'start_line' => $lineNumber,
                        'end_line' => $endLine,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * 构建 PHP 文件的 use import 映射表
     *
     * @param string[] $lines
     * @return array<string, string>
     */
    private function buildImportMap(array $lines): array
    {
        $importMap = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/^use\s+([\w\\\\]+?)(\s+as\s+(\w+))?\s*;/', $line, $matches)) {
                $fullClass = $matches[1];
                $alias = !empty($matches[3]) ? $matches[3] : substr(strrchr($fullClass, '\\'), 1);
                $importMap[$alias] = $fullClass;
            }
        }

        return $importMap;
    }

    /**
     * 清除缓存
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
