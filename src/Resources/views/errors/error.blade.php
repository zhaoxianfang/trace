{{--
    通用 HTTP 错误视图 - 使用统一视图
    支持所有 HTTP 状态码：400, 401, 403, 404, 405, 408, 422, 429, 500, 502, 503, 504
    此视图保持向后兼容，实际渲染由 unified.blade.php 处理
    
    参数：
    - $code: HTTP 状态码 (必需)
    - $message: 自定义错误消息 (可选)
    - $suggestions: 建议/原因列表 (可选数组)
    - $isDebug: 是否显示调试信息 (可选)
    - $exception: 异常对象 (可选，用于调试)
    - $requestId: 请求ID (可选)
    - $timestamp: 时间戳 (可选)
--}}
@php
// 建议列表映射
$suggestionConfig = [
    400 => ['请求参数格式不正确', '缺少必需的参数', '请求体格式错误'],
    401 => ['登录已过期，请重新登录', '未提供有效的认证信息', '账号权限不足'],
    403 => ['未登录或登录已过期', '账号权限不足', '访问了受限资源'],
    404 => ['页面地址输入错误', '页面已被删除或移动', '页面链接已过期'],
    405 => ['使用了错误的 HTTP 方法', '该端点不支持此操作', '请检查 API 文档'],
    408 => ['网络连接不稳定', '服务器响应缓慢', '请求处理时间过长'],
    422 => ['表单数据格式不正确', '必填字段未填写', '数据不符合验证规则'],
    429 => ['请求频率超过限制', '请等待一段时间后重试', '考虑优化请求策略'],
    500 => ['服务器遇到意外错误', '请刷新页面重试', '如果问题持续，请联系管理员'],
    502 => ['上游服务器无响应', '网关配置错误', '请稍后再次访问'],
    503 => ['系统正在维护升级', '服务器负载过高', '请稍后再次访问'],
    504 => ['上游服务器响应超时', '网络连接问题', '请稍后再次访问'],
];

// 合并传入的建议和默认建议
$suggestions = $suggestions ?? ($suggestionConfig[$code] ?? []);
@endphp

@include('trace::errors.unified', [
    'code' => $code,
    'message' => $message ?? null,
    'title' => $title ?? null,
    'emoji' => $emoji ?? null,
    'suggestions' => $suggestions,
    'isDebug' => $isDebug ?? false,
    'exception' => $exception ?? null,
    'requestId' => $requestId ?? null,
    'timestamp' => $timestamp ?? null,
    'mode' => 'full',
])