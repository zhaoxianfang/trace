<?php

namespace zxf\Trace\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use ParseError;
use Throwable;

/**
 * 异常处理Trait
 *
 * 优化说明：
 * - 将静态属性改为实例属性，避免在常驻内存环境下的数据污染和内存泄漏
 * - 每个异常处理实例都拥有独立的状态
 */
trait ExceptionTrait
{
    use ExceptionCodeTrait,ExceptionNotifyTrait;

    // 可使外部调用的处理好的错误码 - 改为实例属性
    protected int $code = 500;

    // 可使外部调用的处理好的错误信息 - 改为实例属性
    protected string $message = '出错啦!';

    // 是否为系统错误 - 改为实例属性
    protected bool $isSysErr = false;

    // 是否为用户错误 - 改为实例属性
    protected bool $isUserErr = false;

    // 是否初始化过异常信息 - 改为实例属性
    protected bool $initErr = false;

    // 错误信息 - 改为实例属性
    protected array $content = [];
    protected ?Throwable $errObj = null;

    /**
     * 获取错误码 - 兼容静态访问方式
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * 获取错误信息 - 兼容静态访问方式
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * 获取初始化错误状态
     */
    public function getInitErr(): bool
    {
        return $this->initErr;
    }

    /**
     * 静态访问错误码 - 兼容旧代码
     */
    public static function getStaticCode(): int
    {
        $instance = app('trace');
        return $instance->code;
    }

    /**
     * 静态访问错误信息 - 兼容旧代码
     */
    public static function getStaticMessage(): string
    {
        $instance = app('trace');
        return $instance->message;
    }

    public function initError(Throwable $e): void
    {
        // 重置所有实例状态，确保每次异常处理都是干净的
        $this->initErr = false;
        $this->isSysErr = false;
        $this->isUserErr = false;
        $this->content = [];

        $this->initErr = true;
        $this->isSysErr = $this->isSystemException($e);
        $this->setStatusCode($e);
        $this->setErrorMessage($e);
        $this->setError($e);

        // 存储当前异常信息，供 Handle::output() 方法使用
        if (property_exists($this, 'currentException')) {
            $this->currentException = $e;
        }
    }

    /**
     * 写入错误日志
     *
     * 注意：检查 request 是否可用，避免在非 HTTP 环境下出错
     *
     * @param  Throwable  $e  异常对象
     */
    public function writeLog(Throwable $e): void
    {
        if (! $this->initErr) {
            $this->initError($e);
        }
        $message = $this->isSysErr ? $e->getMessage() : $this->message;

        // 标记日志已经被记录过了（仅在 HTTP 环境下）
        try {
            if (app()->bound('request') && request()) {
                request()->merge(['log_already_recorded' => true]);
            }
        } catch (\Throwable) {
            // 静默处理，不影响日志记录
        }

        // 记录异常日志
        // try {
        //     Log::error('[异常]:'.$message, self::$content);
        // } catch (Throwable $err) {
        //     // 写入本地文件日志
        //     try {
        //         Log::channel('stack')->error('[异常]:'.$message, self::$content);
        //     } catch (\Throwable) {
        //         // 静默处理，避免无限循环
        //     }
        // }
    }

    /**
     * 获取 HTTP 状态码
     */
    protected function setStatusCode(Throwable $e): int
    {
        if (! $this->initErr) {
            $this->initError($e);
        }
        // 特定异常的状态码映射
        $this->code = match (true) {
            $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface => $e->getStatusCode(),// 如果是 HTTP 异常，使用其状态码
            $e instanceof \Illuminate\Auth\AuthenticationException => 401,
            $e instanceof \Illuminate\Auth\Access\AuthorizationException => 403,
            $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException => 404,
            $e instanceof \Illuminate\Validation\ValidationException => 422,
            $e instanceof \Illuminate\Database\QueryException && str_contains($e->getMessage(), 'Duplicate entry') => 409,
            default => $e->getCode() > 0 ? $e->getCode() : (int) (method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500),
        };

        return $this->code;
    }

    /**
     * 获取用户友好的错误信息
     */
    protected function setErrorMessage(Throwable $e): string
    {
        if (str_contains(strtoupper($e->getMessage()) , 'SQLSTATE')) {
            // 通过错误消息中是否包含 SQLSTATE 字符串来判断是否为 数据库相关异常
            $this->isSysErr = true;
            $this->code = 500; // 设置为 500 系统错误
        }
        if (! $this->isUserErr && (App::environment('production') || $this->isSysErr) && ! config('app.debug')) {
            // 生产环境 || 关闭调试 || 系统错误 => 返回错误码对应的提示信息
            $this->message = $this->getCodeMeg($this->code);
        } else {
            $this->message = $e->getMessage();
        }

        if (empty($this->message)) {
            $this->message = $this->getCodeMeg($this->code);
        }

        return $this->message;
    }

    protected function setError(Throwable $e): array
    {
        if (! $this->initErr) {
            $this->initError($e);
        }
        $this->content = [
            'message:' => $this->message,   // 返回用户自定义的异常信息
            'code:' => $this->code,         // 返回用户自定义的异常代码
            'file:' => $e->getFile(),       // 返回发生异常的PHP程序文件名
            'line:' => $e->getLine(),       // 返回发生异常的代码所在行的行号
            // "trace:"     => $e->getTrace(),      //返回发生异常的传递路线
            // "传递路线String" => $e->getTraceAsString(),//返回发生异常的传递路线
        ];

        $this->errObj = $e; // 记录整个异常信息

        return $this->content;
    }

    /**
     * 判断是否为系统错误
     */
    private function isSystemException(Throwable $exception): bool
    {
        // zxf/tools 扩展包中的错误不算系统错误
        $filePath = $exception->getFile();
        if (str_contains($filePath, 'zxf/tools') || str_contains($filePath, 'zxf\tools')) {
            return false;
        }

        // 空消息 || 包含中文 || 4xx 的错误通常是人为提示或客户端错误，不算系统错误
        if (empty($message = $exception->getMessage()) || preg_match('/[\x{4e00}-\x{9fa5}]/u', $message) || ($exception->getCode() >= 400 && $exception->getCode() < 500)) {
            // 4xx 状态码 (客户端错误) -> 通常是用户/调用方错误
            // 很可能是人为的 abort() 调用 或者用户提交的数据错误等
            $this->isUserErr = true;

            return false;
        }

        // 致命错误
        if ($this->isFatalError($exception)) {
            // 判断是否为致命错误
            return true;
        }

        return match (true) {
            $exception instanceof \ErrorException => true, // 运行时错误
            $exception instanceof \Illuminate\Database\QueryException => true, // 数据库查询错误
            $exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException => true, // 模型未找到
            $exception instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException => true, // 路由未找到
            $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpException => true, // 通用HTTP错误
            $exception instanceof \BadMethodCallException => true, // 当调度的操作方法不存在时
            $exception instanceof \JsonException => true, // json 编码、解码错误
            $exception instanceof \Symfony\Component\HttpFoundation\File\Exception\FileException => false, // 文件上传错误
            $exception instanceof \Illuminate\Validation\ValidationException => false, // 验证错误
            $exception instanceof \Illuminate\Auth\AuthenticationException => false, // 认证错误
            $exception instanceof \Illuminate\Auth\Access\AuthorizationException => false, // 未授权的 Policy 错误
            $exception instanceof \Illuminate\Session\TokenMismatchException => false, // 当 CSRF 令牌不匹配,token 错误
            default => false,
        };
    }

    /**
     * 判断是否为致命错误
     */
    protected function isFatalError(Throwable $exception): bool
    {
        return
            $exception instanceof ParseError // 语法错误，如语法拼写不正确。
            // || $exception instanceof \Error
            || $exception instanceof \TypeError // 类型错误，例如传递的参数类型不符合预期
            || $exception instanceof \DivisionByZeroError // 除以零的错误
            || $exception instanceof \AssertionError // 断言失败的错误
            || $exception instanceof \Symfony\Component\ErrorHandler\Error\FatalError; // 致命错误
        // || $exception instanceof \RuntimeException // 运行时异常，比如操作系统相关错误
    }

    // 是否自定义模块异常接管类
    public function hasModuleCustomException(): bool
    {
        $modulesExceptions = trace_modules_name().'\\'.$this->getModuleName().'\Exceptions\Handler';

        return class_exists($modulesExceptions) && method_exists($modulesExceptions, 'render');
    }

    // 模块下自定义的异常接管类
    public function handleModulesCustomException(Throwable $e, $request)
    {
        // 如果模块下定义了自定义的异常接管类 Handler，则交由模块下的异常类自己处理
        $modulesExceptions = trace_modules_name().'\\'.$this->getModuleName().'\Exceptions\Handler';
        if (class_exists($modulesExceptions) && method_exists($modulesExceptions, 'render')) {
            try {
                if (collect($customRes = call_user_func_array([new $modulesExceptions, 'render'], [$request, $e]))->isNotEmpty()) {
                    if (is_string($customRes)) {
                        $this->showExitMessage($e);
                    }

                    return $customRes;
                }
            } catch (\Exception $err) {
                // 记录错误日志
                $this->writeLog($err);

                // 运行到此处，大概率无法进行响应了, 直接终止运行
                $this->showExitMessage($e);
            }
        }
        return false;
    }

    private function getModuleName(): string
    {
        $moduleName = get_trace_module_name();
        if (empty($moduleName) || strtolower($moduleName) == 'app') {
            $moduleName = get_trace_url_module_name();
        }

        return ucwords($moduleName);
    }

    /**
     * 获取异常代码片段
     *
     * 注意：添加异常处理，确保文件操作安全
     *
     * @return false|string
     */
    private function getExceptionContent(Throwable $e): false|string
    {
        $startLine = $e->getLine() - 4;
        $endLine = $e->getLine() + 4;
        $filePath = $e->getFile();

        // 检查行号是否合理
        if (! is_int($startLine) || ! is_int($endLine) || $startLine <= 0 || $endLine < $startLine) {
            return false;
        }

        // 检查文件是否存在且可读
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            return false;
        }

        // 初始化结果数组和当前行计数器
        $exceptionCode = '';
        $currentLine = 0;
        $file = null;
        $errLine = $e->getLine();

        try {
            // 打开文件
            $file = fopen($filePath, 'r');
            if ($file === false) {
                return false;
            }

            // 循环读取每一行直到到达指定行范围或文件结束
            while (($line = fgets($file)) !== false) {
                $currentLine++;
                if ($currentLine >= $startLine && $currentLine <= $endLine) {
                    // 去除行尾的换行符，并将该行添加到结果数组中
                    if ($currentLine == $errLine) {
                        $exceptionCode .= '<span class="error-line-code" style="color: red;">'.$currentLine.'|'.$line.'</span>';
                    } else {
                        $exceptionCode .= $currentLine.'|'.$line;
                    }
                }
                if ($currentLine > $endLine) {
                    break; // 如果已经超过了所需的最后一行，则停止读取
                }
            }
        } catch (\Throwable) {
            // 文件操作失败，返回 false
            return false;
        } finally {
            // 确保文件句柄被关闭
            if ($file !== null && is_resource($file)) {
                fclose($file);
            }
        }

        // 返回结果数组
        return $exceptionCode;
    }

    // 显示错误信息
    public function debug(Throwable $e): Response|JsonResponse
    {
        if (! $this->initErr) {
            $this->initError($e);
        }

        $content = [
            [
                'label' => '异常信息',
                'type' => 'text',
                'value' => $this->message,
            ], [
                'label' => '状态码',
                'type' => 'text',
                'value' => $this->code,
            ], [
                'label' => '异常文件',
                'type' => 'debug_file',
                'value' => str_replace(base_path(), '', $e->getFile()).':'.$e->getLine().' (行)',
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], [
                'label' => '异常代码',
                'type' => 'code',
                'value' => $this->getExceptionContent($e),
            ], [
                'label' => '异常堆栈',
                'type' => 'code',
                'value' => str_replace(base_path(), '', $e->getTraceAsString()),
            ],
        ];
        // 如果是语法错误，$showTrace 为 true 就会陷入死循环
        $showTrace = ! $e instanceof ParseError;

        return $this->outputDebugHtml($content, $this->code.':'.$this->message, $this->code, $showTrace);
    }
}
