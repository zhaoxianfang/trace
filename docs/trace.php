<?php

/*
 |--------------------------------------------------------------------------
 | Trace 代码调试和错误处理
 |--------------------------------------------------------------------------
 |
 | 下面这个 trace 配置是可选的；可以自定义配置trace的每一项，也不可以不配置任意一项，甚至
 | 不需要你单独定trace配置项，zxf/trace 中会使用默认配置处理。
 |
 */

return [
    /**
     * 是否开启 trace 功能
     * 默认: true
     */
    'enabled'=>true,

    /**
     * 使用自定义处理的命名空间，例如在 App\Exceptions\Handler->render 中自定义处理 Trace 检测到的异常
     * 有些项目使用 Modules 多模块，这个配置会变得很有用
     *
     * 默认: App
     */
    'namespace'=>'App',

    /**
     * 自定义处理 Trace 调试产生的数据
     * 默认:空
     *    例如:
     *    'end_handle_class' => \App\Services\TraceEndService::class,
     *    // 表示在 TraceEndHandle 类中接管 Trace 调试产生的数据
     *
     *    use Illuminate\Support\Facades\Log;
     *
     *    class TraceEndService
     *    {
     *        public function handle(array $trace=[]): void
     *        {
     *            // 做点什么...
     *            // Log::channel('stack')->debug('===== [Trace]调试: ===== ', $trace);
     *        }
     *    }
     */
    'end_handle_class'=>'',

    /*
    |--------------------------------------------------------------------------
    | 代码追踪调试使用的编辑器
    |--------------------------------------------------------------------------
    |
    | 设置代码调试编辑器，调试工具会引导点击链接跳转到编辑器的指定位置，
    | 默认: phpstorm
    |
    | 支持: "phpstorm", "vscode", "vscode-insiders", "vscode-remote",
    |            "vscode-insiders-remote", "vscodium", "textmate", "emacs",
    |            "sublime", "atom", "nova", "macvim", "idea", "netbeans",
    |            "xdebug", "espresso"
    |
    */
    'editor' => 'phpstorm',

];
