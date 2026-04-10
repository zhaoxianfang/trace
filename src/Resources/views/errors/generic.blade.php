{{--
    通用错误视图 - 使用统一视图（兼容旧版本）
    此视图已弃用，请使用 trace::errors.error 或 trace::errors.unified
    
    参数：
    - $code: HTTP 状态码 (必需)
    - $message: 自定义错误消息 (可选)
    - $isDebug: 是否显示调试信息 (可选)
    - $exception: 异常对象 (可选，用于调试)
--}}
@include('trace::errors.unified', [
    'code' => $code,
    'message' => $message ?? null,
    'isDebug' => $isDebug ?? false,
    'exception' => $exception ?? null,
    'mode' => 'compact',
])