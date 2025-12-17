<?php

use zxf\Trace\Handle;

if (! function_exists('is_enable_trace')) {
    /**
     * 判断是否开启 trace 调试
     */
    function is_enable_trace(): bool
    {
        // return !app()->runningInConsole() && !app()->environment('testing') && request()->isMethod('get');
        return !app()->runningInConsole() && !app()->environment('production') && !request()->expectsJson();
        // return ! app()->runningInConsole() && ! is_resource_file(request()->fullUrl(), true);
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