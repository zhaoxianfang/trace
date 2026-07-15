<?php

if (! function_exists('is_enable_trace')) {
    /**
     * 判断是否开启 trace 调试
     *
     * 注意：在非 HTTP 环境下（如命令行）返回 false
     *
     * @return bool true 表示启用 trace 调试
     */
    function is_enable_trace(): bool
    {
        try {
            $app = app();

            // 命令行下关闭 trace 调试
            if ($app->runningInConsole()) {
                return false;
            }

            // [最高优先级]如果在 config 文件夹下的 trace.php 文件中配置了 enabled 为 true|false 则使用此配置
            $traceEnabled = config('trace.enabled');
            if (is_bool($traceEnabled)) {
                return $traceEnabled;
            }

            // 检查 request 是否可用
            if (! $app->bound('request')) {
                return false;
            }

            $request = request();
            if (! $request instanceof \Illuminate\Http\Request) {
                return false;
            }

            // 非生产环境或开启调试模式，且非 JSON 请求，且非静态文件
            return (! $app->environment('production') || config('app.debug'))
                && ! $request->expectsJson()
                && ! is_static_file($request->fullUrl(), true);
        } catch (\Throwable) {
            // 出现任何异常时返回 false，避免影响主流程
            return false;
        }
    }
}

if (! function_exists('trace')) {
    /**
     * 调试代码
     *
     * @param  mixed  ...$args  调试任意个参数
     *                          eg：trace('hello', 'world');
     *                          trace(['hello', 'world']);
     * @return void
     */
    function trace(mixed ...$args): void
    {
        // 如果 trace 未启用或服务未注册，直接跳过，不产生副作用
        if (! is_enable_trace() || ! app()->bound('trace')) {
            return;
        }

        /** @var \zxf\Trace\Handle $trace */
        $trace = app('trace');
        foreach ($args as $value) {
            $trace->addMessage($value, 'debug');
        }
    }
}

if (! function_exists('is_static_file')) {
    /**
     * 判断是否是资源文件[文件后缀判断]
     *
     * @param  string  $url  URL 地址
     * @param  bool|array  $simpleOrCustomExt  仅判断简单的几种资源文件
     *                                         true(默认): 仅判断简单的几种资源文件
     *                                         false: 会判断大部分的资源文件
     *                                         array: 仅判断自定义的这些后缀
     * @return bool
     */
    function is_static_file(string $url, bool|array $simpleOrCustomExt = true): bool
    {
        // 解析 URL
        $path = parse_url($url, PHP_URL_PATH);

        // 检查 path 是否有效
        if (! is_string($path) || $path === '') {
            return false;
        }

        // 获取文件扩展名
        $ext = pathinfo($path, PATHINFO_EXTENSION);

        // bool: 使用预定义的后缀和特殊规则进行判断
        if (is_bool($simpleOrCustomExt)) {
            // 是否简单判断
            $resourceExtList = $simpleOrCustomExt
                ? ['js', 'css', 'ico', 'ttf', 'jpg', 'jpeg', 'png', 'webp']
                : [
                    'js', 'css', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'ico', 'webp', 'ttf', 'woff', 'woff2',
                    'eot', 'otf', 'mp3', 'mp4', 'wav', 'wma', 'wmv', 'avi', 'mpg', 'mpeg', 'rm', 'rmvb', 'flv',
                    'swf', 'mkv', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip',
                    'rar', '7z', 'tar', 'gz', 'bz2', 'tgz', 'tbz', 'tbz2', 'tb2', 't7z', 'jar', 'war', 'ear', 'zipx',
                    'apk', 'ipa', 'exe', 'dmg', 'pkg', 'deb', 'rpm', 'msi', 'md', 'log',
                ];

            if (! empty($ext)) {
                // 检查扩展名是否属于资源文件类型
                return in_array(strtolower($ext), $resourceExtList, true);
            }

            // 或者一些特殊路由前缀资源：captcha/: 验证码；tn_code/: 滑动验证码
            $trimmedPath = trim($path, '/');
            return str_starts_with($trimmedPath, 'captcha/') || str_starts_with($trimmedPath, 'tn_code/');
        }

        // array: 全部采用自定义传入的扩展名进行判断
        if (! is_array($simpleOrCustomExt) || empty($ext)) {
            return false;
        }

        // 转换所有扩展名为小写进行比较
        $customExts = array_map('strtolower', $simpleOrCustomExt);
        return in_array(strtolower($ext), $customExts, true);
    }
}


if (! function_exists('size_format')) {
    /**
     * 文件字节转具体大小
     *
     * 优化内容：
     * 1. 支持二进制和十进制单位
     * 2. 改进精度控制
     * 3. 添加自定义单位支持
     *
     * @param int $size 文件字节
     * @param int $dec 小数位数
     * @param bool $binary 是否使用二进制单位 (true: KiB, MiB; false: KB, MB)
     * @return string
     */
    function size_format(int $size, int $dec = 2, bool $binary = false): string
    {
        // 处理边界情况
        if ($size < 0) {
            return 'Invalid';
        }

        if ($size === 0) {
            return '0 B';
        }

        $base = $binary ? 1024 : 1000;
        $units = $binary ?
            ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'] :
            ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

        $pos = 0;
        $formattedSize = $size;

        while ($formattedSize >= $base && $pos < count($units) - 1) {
            $formattedSize /= $base;
            $pos++;
        }

        // 确保精度不丢失
        $formattedSize = round($formattedSize, $dec);

        return number_format($formattedSize, $dec, '.', '') . ' ' . $units[$pos];
    }
}


if (! function_exists('get_trace_module_name')) {
    /**
     * 获取当前所在模块
     *
     * 在 Modules 模块里面 获取当前所在模块名称
     * 注意，需要在 Modules 里面调用，否则返回 App
     *
     * @param  bool  $toUnderlineConvert  是否转换为 驼峰+小写 模式
     * @return mixed|string
     */
    function get_trace_module_name(?bool $toUnderlineConvert = false): string
    {
        if (function_exists('module_name')) {
            return module_name();
        }
        try {
            if (app()->runningInConsole()) {
                return $toUnderlineConvert ? 'command' : 'Command';
            }
            if (! empty($request = request()) && ! empty($route = $request->route())) {
                // Laravel 11+ 的 Route::getAction() 不再保证包含 'namespace' 键，
                // 使用带默认值的 getAction($key, $default) 并做字符串校验，
                // 避免 "Undefined array key" 警告以及 explode(null) 抛出 TypeError
                $routeNamespace = $route->getAction('namespace') ?? '';
                if (is_string($routeNamespace) && $routeNamespace !== '') {
                    $modulesNamespaceArr = array_filter(explode('\\', explode('Http\Controllers', $routeNamespace)[0]));
                    // 判断 $route->uri() 字符串中是否包含 无路由回调fallback ||
                    if (! str_contains($route->uri(), 'fallback') && ! empty($modulesNamespaceArr) && ($modulesNamespaceArr[0] ?? '') == trace_modules_name()) {
                        $moduleName = $modulesNamespaceArr[1] ?? '';
                        if ($moduleName !== '') {
                            return $toUnderlineConvert ? strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $moduleName)) : $moduleName;
                        }
                    }
                }
            }
            if (! empty($request = request())) {
                // 获取 $request->path() 中第一个 / 之前的字符串
                if ($res = strstr(trim($request->path(), '/'), '/', true)) {
                    return $res;
                }
            }

            return $toUnderlineConvert ? 'app' : 'App';
        } catch (\Throwable $err) {
            return get_trace_url_module_name($toUnderlineConvert);
        }
    }
}

if (! function_exists('get_trace_url_module_name')) {
    /**
     * 获取 url 中的模块名称(url前缀模块名称), 例如：http://www.xxx.com/docs/xxx/xxx/xxx 中的 docs
     */
    function get_trace_url_module_name(?bool $toUnderlineConvert = false): string
    {
        $module = str(request()->path())->before('/')->lower()->value() ?: 'app';

        return $toUnderlineConvert ? $module : \Illuminate\Support\Str::studly($module);
    }
}

if (! function_exists('trace_modules_name')) {
    /**
     * 获取多模块的文件夹名称（默认：Modules）
     *
     * @return string 返回配置的模块命名空间或默认值
     */
    function trace_modules_name(): string
    {
        if (function_exists('modules_name')) {
            return modules_name();
        }
        return config('trace.namespace', 'Modules');
    }
}


if (! function_exists('set_protected_attr')) {
    /**
     * 使用反射 修改对象里面受保护属性的值
     *
     * 注意：PHP 8.1+ 中 setAccessible() 不再是必需的，从 PHP 8.1 开始默认为 true
     *
     * @throws ReflectionException
     */
    function set_protected_attr($obj, $filed, $value): void
    {
        $reflectionClass = new ReflectionClass($obj);
        try {
            $reflectionClass->setStaticPropertyValue($filed, $value);
        } catch (\Throwable $err) {
            $reflectionProperty = $reflectionClass->getProperty($filed);
            // PHP 8.1+ 不再需要 setAccessible()，默认为 true
            if (PHP_VERSION_ID < 80100) {
                $reflectionProperty->setAccessible(true);
            }
            $reflectionProperty->setValue($obj, $value);
        }
    }
}

if (! function_exists('format_param')) {
    /**
     * 显示变量的值；例如 布尔类型的true 显示为字符串的TRUE
     *
     * @param  mixed  $value
     * @return string
     */
    function format_param(mixed $value): string
    {
        return match (true) {
            $value === ''     => '\'\'',
            $value === null   => 'NULL',
            is_bool($value)   => $value ? 'TRUE' : 'FALSE',
            is_string($value) => $value,
            is_scalar($value) => (string)$value,
            default => trim(var_export($value, true), "'")
        };
    }
}
