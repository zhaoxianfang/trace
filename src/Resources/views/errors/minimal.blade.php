{{--
    极简错误视图 - 用于极端情况（框架完全不可用）
    此视图使用统一视图的极简模式
    
    参数：
    - $code: HTTP 状态码 (可选，默认500)
    - $title: 标题 (可选)
    - $message: 错误消息 (可选)
--}}
@include('trace::errors.unified', [
    'code' => $code ?? 500,
    'title' => $title ?? '系统错误',
    'message' => $message ?? '系统发生错误，请稍后重试',
    'mode' => 'minimal',
])