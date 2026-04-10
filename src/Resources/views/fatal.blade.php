{{--
    致命错误视图 - 使用统一视图
    此视图现在使用统一视图系统
    
    参数：
    - $code: HTTP 状态码 (可选，默认500)
    - $title: 标题 (可选)
    - $message: 错误消息 (可选)
    - $debugInfo: 调试信息 (可选)
--}}
@include('trace::errors.unified', [
    'code' => $code ?? 500,
    'title' => $title ?? '系统错误',
    'message' => $message ?? '系统发生严重错误',
    'mode' => 'full',
])
