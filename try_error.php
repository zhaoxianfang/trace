<?php
/**
 * 终极全局异常拦截器 - 零配置、不修改任何环境、纯拦截输出
 * 支持跨平台（Windows/Mac/Linux），不修改任何 ini 配置
 */
final class PureExceptionInterceptor {
    private static $instance = null;
    private static $isActive = false;
    private static $interceptedException = null;
    private static $originalErrorHandler = null;
    private static $originalExceptionHandler = null;
    private static $executionId = null;
    private static $startTime = null;
    private static $inIsolatedProcess = false;
    
    /**
     * 私有构造函数
     */
    private function __construct() {
        self::$executionId = uniqid('int_', true);
        self::$startTime = microtime(true);
    }
    
    /**
     * 获取单例实例
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 启动全局拦截（不修改任何配置）
     */
    public static function intercept() {
        if (self::$isActive) {
            return;
        }
        
        $instance = self::getInstance();
        
        // 检测是否在隔离进程中
        if (self::$inIsolatedProcess) {
            $instance->setupErrorHandlers();
            self::$isActive = true;
            return;
        }
        
        // 尝试进程隔离（仅Unix/Linux/Mac）
        if ($instance->canUseProcessIsolation() && !self::$inIsolatedProcess) {
            $instance->startIsolatedProcess();
        } else {
            $instance->setupErrorHandlers();
        }
        
        self::$isActive = true;
    }
    
    /**
     * 检查是否可以使用进程隔离
     */
    private function canUseProcessIsolation() {
        return function_exists('pcntl_fork') && 
               function_exists('pcntl_waitpid') && 
               function_exists('pcntl_wifexited') &&
               function_exists('pcntl_wifsignaled') &&
               function_exists('pcntl_wtermsig') &&
               PHP_OS_FAMILY !== 'Windows';
    }
    
    /**
     * 启动隔离进程
     */
    private function startIsolatedProcess() {
        $pid = pcntl_fork();
        
        if ($pid == -1) {
            // Fork失败，使用普通处理器
            $this->setupErrorHandlers();
            return;
        }
        
        if ($pid == 0) {
            // 子进程：标记隔离模式并执行
            self::$inIsolatedProcess = true;
            $this->setupErrorHandlers();
            return;
        } else {
            // 父进程：监控子进程
            $this->monitorChildProcess($pid);
        }
    }
    
    /**
     * 监控子进程
     */
    private function monitorChildProcess($pid) {
        $status = null;
        $timeout = 30;
        $start = time();
        
        // 等待子进程结束
        while (time() - $start < $timeout) {
            $res = pcntl_waitpid($pid, $status, WNOHANG);
            if ($res == -1 || $res > 0) {
                break;
            }
            usleep(100000);
        }
        
        // 检查子进程是否还在运行
        if ($res === 0) {
            // 子进程超时，发送终止信号
            posix_kill($pid, SIGTERM);
            usleep(100000);
            
            // 如果还在运行，强制杀死
            if (posix_kill($pid, 0)) {
                posix_kill($pid, SIGKILL);
            }
            pcntl_waitpid($pid, $status, 0);
            
            $this->outputIntercepted([
                'type' => 'PROCESS_TIMEOUT',
                'message' => '脚本执行超时，可能是内存溢出、无限递归或死循环导致',
                'timeout_seconds' => $timeout
            ]);
            exit(1);
        }
        
        // 检查进程退出信号
        if (pcntl_wifsignaled($status)) {
            $signal = pcntl_wtermsig($status);
            $this->handleProcessSignal($signal);
        } 
        // 检查退出码
        elseif (pcntl_wifexited($status)) {
            $exitCode = pcntl_wexitstatus($status);
            if ($exitCode !== 0 && self::$interceptedException === null) {
                $this->outputIntercepted([
                    'type' => 'PROCESS_EXIT_ERROR',
                    'message' => '进程异常退出',
                    'exit_code' => $exitCode
                ]);
                exit(1);
            }
        }
        
        // 输出拦截到的异常
        if (self::$interceptedException !== null) {
            $this->outputIntercepted(self::$interceptedException);
            exit(1);
        }
    }
    
    /**
     * 处理进程信号
     */
    private function handleProcessSignal($signal) {
        $signalMap = [
            11 => [
                'type' => 'SEGMENTATION_FAULT',
                'message' => '段错误：检测到深层嵌套数组、无限递归或内存溢出'
            ],
            6 => [
                'type' => 'PROCESS_ABORTED',
                'message' => '进程中止：内存耗尽或程序异常'
            ],
            4 => [
                'type' => 'ILLEGAL_INSTRUCTION',
                'message' => '非法指令：可能是栈溢出或代码段损坏'
            ],
            8 => [
                'type' => 'FLOATING_POINT_EXCEPTION',
                'message' => '浮点异常：数学运算错误'
            ],
            15 => [
                'type' => 'PROCESS_TERMINATED',
                'message' => '进程被终止'
            ],
            9 => [
                'type' => 'PROCESS_KILLED',
                'message' => '进程被强制终止'
            ]
        ];
        
        $info = $signalMap[$signal] ?? [
            'type' => 'PROCESS_SIGNAL_ERROR',
            'message' => "进程被信号 {$signal} 终止"
        ];
        
        $this->outputIntercepted($info);
        exit(1);
    }
    
    /**
     * 设置错误处理器（不修改任何配置）
     */
    private function setupErrorHandlers() {
        // 保存原始处理器
        self::$originalErrorHandler = set_error_handler(function($errno, $errstr, $errfile, $errline) {
            return $this->handleError($errno, $errstr, $errfile, $errline);
        });
        
        // 保存原始异常处理器
        self::$originalExceptionHandler = set_exception_handler(function($exception) {
            $this->handleException($exception);
        });
        
        // 注册关闭函数
        register_shutdown_function(function() {
            $this->handleShutdown();
        });
    }
    
    /**
     * 处理错误
     */
    private function handleError($errno, $errstr, $errfile, $errline) {
        // 忽略通知和弃用错误
        $ignoredTypes = [E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED, E_STRICT];
        if (in_array($errno, $ignoredTypes)) {
            return false;
        }
        
        self::$interceptedException = [
            'type' => $this->getErrorTypeName($errno),
            'code' => $errno,
            'message' => $errstr,
            'file' => $this->shortenPath($errfile),
            'line' => $errline,
            'timestamp' => date('Y-m-d H:i:s'),
            'execution_id' => self::$executionId
        ];
        
        return true; // 阻止默认处理
    }
    
    /**
     * 处理异常
     */
    private function handleException($exception) {
        self::$interceptedException = [
            'type' => get_class($exception),
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'file' => $this->shortenPath($exception->getFile()),
            'line' => $exception->getLine(),
            'timestamp' => date('Y-m-d H:i:s'),
            'execution_id' => self::$executionId
        ];
        
        // 不输出，等待 shutdown 时统一输出
    }
    
    /**
     * 处理致命错误
     */
    private function handleShutdown() {
        $error = error_get_last();
        
        if ($error !== null) {
            $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if (in_array($error['type'], $fatalTypes)) {
                self::$interceptedException = [
                    'type' => $this->getErrorTypeName($error['type']),
                    'code' => $error['type'],
                    'message' => $error['message'],
                    'file' => $this->shortenPath($error['file']),
                    'line' => $error['line'],
                    'timestamp' => date('Y-m-d H:i:s'),
                    'execution_id' => self::$executionId
                ];
            }
        }
        
        // 输出拦截到的异常
        if (self::$interceptedException !== null) {
            $this->outputIntercepted(self::$interceptedException);
            exit(1);
        }
    }
    
    /**
     * 输出拦截信息（不抛出原始异常）
     */
    private function outputIntercepted($exception) {
        // 清理输出缓冲区
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // 构建拦截信息
        $interceptMessage = $this->buildInterceptMessage($exception);
        
        // 输出拦截信息
        if (PHP_SAPI === 'cli') {
            echo $interceptMessage;
        } else {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
            }
            echo json_encode(['intercepted' => true, 'message' => $interceptMessage], JSON_UNESCAPED_UNICODE);
        }
    }
    
    /**
     * 构建拦截消息
     */
    private function buildInterceptMessage($exception) {
        $type = $exception['type'];
        $message = $exception['message'];
        $file = $exception['file'] ?? 'unknown';
        $line = $exception['line'] ?? '?';
        $time = $exception['timestamp'] ?? date('Y-m-d H:i:s');
        $execId = $exception['execution_id'] ?? self::$executionId;
        
        // 判断错误类型
        $errorCategory = $this->categorizeError($type, $message);
        
        $lines = [];
        $lines[] = '';
        $lines[] = '╔══════════════════════════════════════════════════════════════════╗';
        $lines[] = '║                    🔒 异常拦截器捕获到错误                        ║';
        $lines[] = '╚══════════════════════════════════════════════════════════════════╝';
        $lines[] = '';
        $lines[] = '  📋 追踪ID: ' . $execId;
        $lines[] = '  ⏰ 发生时间: ' . $time;
        $lines[] = '  🏷️  错误类别: ' . $errorCategory['category'];
        $lines[] = '  🔍 错误类型: ' . $type;
        $lines[] = '  💬 错误描述: ' . $message;
        $lines[] = '  📁 文件位置: ' . $file . ':' . $line;
        $lines[] = '';
        $lines[] = '  💡 建议措施: ' . $errorCategory['suggestion'];
        $lines[] = '';
        $lines[] = '──────────────────────────────────────────────────────────────────';
        $lines[] = '⚠️  已拦截异常，原始错误未抛出';
        $lines[] = '──────────────────────────────────────────────────────────────────';
        $lines[] = '';
        
        return implode("\n", $lines);
    }
    
    /**
     * 分类错误并提供建议
     */
    private function categorizeError($type, $message) {
        $messageLower = strtolower($message);
        
        // 内存相关
        if (strpos($messageLower, 'memory') !== false || 
            strpos($messageLower, 'allowed memory size') !== false) {
            return [
                'category' => 'MEMORY_OVERFLOW',
                'suggestion' => '检查是否存在深层嵌套数组、无限递归或大数据集操作'
            ];
        }
        
        // 嵌套/递归相关
        if (strpos($messageLower, 'nesting') !== false || 
            strpos($messageLower, 'recursion') !== false ||
            strpos($messageLower, 'stack overflow') !== false) {
            return [
                'category' => 'NESTING_OVERFLOW',
                'suggestion' => '检查函数递归深度、数组嵌套层级或循环引用'
            ];
        }
        
        // 执行超时
        if (strpos($messageLower, 'timeout') !== false || 
            strpos($messageLower, 'max execution time') !== false) {
            return [
                'category' => 'EXECUTION_TIMEOUT',
                'suggestion' => '检查是否存在死循环、低效算法或超大数据处理'
            ];
        }
        
        // 解析错误
        if (strpos($messageLower, 'parse') !== false || 
            strpos($messageLower, 'syntax error') !== false) {
            return [
                'category' => 'PARSE_ERROR',
                'suggestion' => '检查代码语法错误、括号匹配或引号使用'
            ];
        }
        
        // 类型错误
        if (strpos($messageLower, 'type error') !== false || 
            strpos($type, 'TypeError') !== false) {
            return [
                'category' => 'TYPE_ERROR',
                'suggestion' => '检查变量类型、函数参数类型或返回值类型'
            ];
        }
        
        // 默认
        return [
            'category' => 'RUNTIME_ERROR',
            'suggestion' => '检查代码逻辑、变量定义或外部依赖'
        ];
    }
    
    /**
     * 获取错误类型名称
     */
    private function getErrorTypeName($errno) {
        $types = [
            E_ERROR => 'FATAL_ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE_ERROR',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT_WARNING',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED'
        ];
        
        return $types[$errno] ?? 'UNKNOWN_ERROR';
    }
    
    /**
     * 缩短文件路径
     */
    private function shortenPath($path) {
        if (empty($path)) {
            return 'unknown';
        }
        
        // 只显示最后3级目录
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        if (count($parts) > 3) {
            $parts = array_slice($parts, -3);
            return '...' . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
        }
        
        return $path;
    }
    
    /**
     * 获取拦截的异常
     */
    public static function getInterceptedException() {
        return self::$interceptedException;
    }
    
    /**
     * 是否有拦截
     */
    public static function hasIntercepted() {
        return self::$interceptedException !== null;
    }
    
    /**
     * 重置状态
     */
    public static function reset() {
        self::$interceptedException = null;
        self::$isActive = false;
        self::$executionId = uniqid('int_', true);
        self::$startTime = microtime(true);
    }
}

/**
 * 全局便捷函数
 */
function intercept_all() {
    PureExceptionInterceptor::intercept();
}

function has_intercepted() {
    return PureExceptionInterceptor::hasIntercepted();
}

function get_intercepted() {
    return PureExceptionInterceptor::getInterceptedException();
}

// ========== 自动启动 ==========
intercept_all();

/**
 * [场景33]: 深层嵌套数组导致内存溢出
 * 测试代码 - 完全不修改原函数
 */
function test_error() {
    $deepArray = [];
    for ($i = 0; $i < 1000000; $i++) {
        $deepArray = [$deepArray];
    }
}

// 执行测试
test_error();
?>