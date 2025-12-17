<?php

use zxf\Trace\Handle;

if (! function_exists('is_enable_trace')) {
    /**
     * 判断是否开启 trace 调试
     */
    function is_enable_trace(): bool
    {
        return !app()->runningInConsole() && (!app()->environment('production') || config('app.debug')) && !request()->expectsJson() && ! is_static_file(request()->fullUrl(), true);
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
    function trace(mixed ...$args)
    {
        /** @var $trace Handle */
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
     * @param  bool|array  $simpleOrCustomExt  仅判断简单的几种资源文件
     *                                         true(默认): 仅判断简单的几种资源文件
     *                                         false: 会判断大部分的资源文件
     *                                         array: 仅判断自定义的这些后缀
     */
    function is_static_file(string $url, bool|array $simpleOrCustomExt = true): bool
    {
        // 解析 URL
        $path = parse_url($url, PHP_URL_PATH);
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
                    'swf', 'mkv', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip',
                    'rar', '7z', 'tar', 'gz', 'bz2', 'tgz', 'tbz', 'tbz2', 'tb2', 't7z', 'jar', 'war', 'ear', 'zipx',
                    'apk', 'ipa', 'exe', 'dmg', 'pkg', 'deb', 'rpm', 'msi', 'md', 'txt', 'log',
                ];
            if (! empty($ext)) {
                // 检查扩展名是否属于资源文件类型
                return in_array(strtolower($ext), $resourceExtList);
            }

            // 或者一些特殊路由前缀资源：captcha/: 验证码；tn_code/: 滑动验证码
            return str_starts_with(trim($path, '/'), 'captcha/') || str_starts_with(trim($path, '/'), 'tn_code/');
        }

        // array: 全部采用自定义传入的扩展名进行判断
        // 传值不为空?检查扩展名是否属于资源文件类型:false
        return ! empty($ext) && in_array(strtolower($ext), $simpleOrCustomExt);
    }
}