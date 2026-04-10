{{--
    紧急错误视图
    用于 EmergencyRenderer 和 FallbackExceptionHandler 降级场景
    此视图现在使用统一视图系统
    
    参数：
    - $code: HTTP 状态码 (可选，默认500)
    - $title: 标题 (可选)
    - $message: 错误消息 (可选)
    - $emoji: 表情符号 (可选)
    - $isDebug: 是否显示调试信息 (可选)
    - $exception: 异常对象 (可选)
    - $requestId: 请求ID (可选)
    - $timestamp: 时间戳 (可选)
--}}
@include('trace::errors.unified', [
    'code' => $code ?? 500,
    'title' => $title ?? '系统错误',
    'message' => $message ?? '系统发生错误，请稍后重试或联系管理员',
    'emoji' => $emoji ?? null,
    'isDebug' => $showDebug ?? $isDebug ?? false,
    'exception' => $exception ?? null,
    'requestId' => $requestId ?? null,
    'timestamp' => $timestamp ?? null,
    'mode' => 'full',
])
