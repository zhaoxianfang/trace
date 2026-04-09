{{-- 极简错误视图 - 用于极端情况（框架完全不可用） --}}
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? '系统错误' }} - {{ $code ?? 500 }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
            text-align: center;
            animation: slideIn 0.5s ease-out;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .code {
            font-size: 72px;
            font-weight: bold;
            color: #e53e3e;
            line-height: 1;
            margin-bottom: 15px;
        }
        .title {
            font-size: 22px;
            color: #2d3748;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .message {
            font-size: 15px;
            color: #718096;
            margin-bottom: 25px;
            line-height: 1.6;
            word-break: break-word;
        }
        .btn {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            background: #667eea;
            color: white;
        }
        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        @media (max-width: 480px) {
            .container { padding: 30px 20px; }
            .code { font-size: 56px; }
            .title { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="code">{{ $code ?? 500 }}</div>
        <h1 class="title">{{ $title ?? '系统错误' }}</h1>
        <p class="message">{{ $message ?? '系统发生错误，请稍后重试' }}</p>
        <a href="/" class="btn">返回首页</a>
    </div>
</body>
</html>
