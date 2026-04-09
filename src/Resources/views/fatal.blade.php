@extends('trace::layouts.error')

@section('title', $title ?? '系统错误')

@section('head-extra')
<style>
    .fatal-container {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        padding: 20px;
        position: relative;
    }

    .fatal-svg {
        position: absolute;
        top: 50%;
        left: 50%;
        margin-top: -250px;
        margin-left: -400px;
        opacity: 0.3;
        z-index: 0;
    }

    .message-box {
        height: auto;
        width: 380px;
        position: relative;
        z-index: 1;
        color: #FFF;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        font-weight: 300;
        text-align: center;
        background: rgba(47, 50, 66, 0.9);
        padding: 40px;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(10px);
    }

    .message-box h1 {
        font-size: 72px;
        line-height: 1;
        margin-bottom: 20px;
        background: linear-gradient(135deg, #007fb2, #4ecdc4);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .message-box .subtitle {
        font-size: 18px;
        margin-bottom: 15px;
        color: #e0e0e0;
    }

    .message-box .error-message {
        font-size: 14px;
        color: #ff9f9f;
        margin-bottom: 20px;
        padding: 10px;
        background: rgba(255, 107, 107, 0.1);
        border-radius: 6px;
        border-left: 3px solid #ff6b6b;
        word-break: break-word;
    }

    .message-box .debug-info {
        font-size: 12px;
        color: #a0a0a0;
        margin-bottom: 20px;
        text-align: left;
        padding: 15px;
        background: rgba(0, 0, 0, 0.2);
        border-radius: 6px;
    }

    .message-box .debug-info p {
        margin: 5px 0;
        line-height: 1.5;
    }

    .buttons-con {
        margin-top: 30px;
    }

    .buttons-con .action-link-wrap {
        display: flex;
        gap: 12px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .buttons-con .action-link-wrap a {
        background: linear-gradient(135deg, #007fb2, #0099cc);
        padding: 12px 28px;
        border-radius: 6px;
        color: #FFF;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        border: none;
    }

    .buttons-con .action-link-wrap a:hover {
        background: linear-gradient(135deg, #0099cc, #007fb2);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 127, 178, 0.4);
    }

    .buttons-con .action-link-wrap a:last-child {
        background: transparent;
        border: 2px solid #007fb2;
    }

    .buttons-con .action-link-wrap a:last-child:hover {
        background: rgba(0, 127, 178, 0.2);
    }

    /* 多边形动画 */
    #Polygon-1, #Polygon-2, #Polygon-3, #Polygon-4, #Polygon-5 {
        animation: float 1s infinite ease-in-out alternate;
    }

    #Polygon-2 {
        animation-delay: 0.2s;
    }

    #Polygon-3 {
        animation-delay: 0.4s;
    }

    #Polygon-4 {
        animation-delay: 0.6s;
    }

    #Polygon-5 {
        animation-delay: 0.8s;
    }

    @keyframes float {
        100% {
            transform: translateY(20px);
        }
    }

    /* 响应式设计 */
    @media (max-width: 768px) {
        .fatal-svg {
            display: none;
        }

        .message-box {
            width: 100%;
            max-width: 380px;
            padding: 30px 20px;
        }

        .message-box h1 {
            font-size: 56px;
        }
    }
</style>
@endsection

@section('content')
<div class="fatal-container">
    <!-- 背景装饰 SVG -->
    <svg class="fatal-svg" width="380px" height="500px" viewBox="0 0 837 1045" version="1.1" xmlns="http://www.w3.org/2000/svg">
        <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
            <path d="M353,9 L626.664028,170 L626.664028,487 L353,642 L79.3359724,487 L79.3359724,170 L353,9 Z" id="Polygon-1" stroke="#007FB2" stroke-width="6"></path>
            <path d="M78.5,529 L147,569.186414 L147,648.311216 L78.5,687 L10,648.311216 L10,569.186414 L78.5,529 Z" id="Polygon-2" stroke="#EF4A5B" stroke-width="6"></path>
            <path d="M773,186 L827,217.538705 L827,279.636651 L773,310 L719,279.636651 L719,217.538705 L773,186 Z" id="Polygon-3" stroke="#795D9C" stroke-width="6"></path>
            <path d="M639,529 L773,607.846761 L773,763.091627 L639,839 L505,763.091627 L505,607.846761 L639,529 Z" id="Polygon-4" stroke="#F2773F" stroke-width="6"></path>
            <path d="M281,801 L383,861.025276 L383,979.21169 L281,1037 L179,979.21169 L179,861.025276 L281,801 Z" id="Polygon-5" stroke="#36B455" stroke-width="6"></path>
        </g>
    </svg>

    <div class="message-box">
        <h1>{{ $code ?? 500 }}</h1>
        <p class="subtitle">[{{ config('app.name', '系统') }}] 提示您，出错啦！</p>
        
        <p class="error-message">{{ $message ?? '系统发生错误' }}</p>
        
        @if(!empty($debugInfo))
            <div class="debug-info">
                {!! $debugInfo !!}
            </div>
        @endif
        
        <div class="buttons-con">
            <div class="action-link-wrap">
                <a onclick="history.back(-1)" class="link-button link-back-button">返回上一页</a>
                <a href="/" class="link-button">返回首页</a>
            </div>
        </div>
    </div>
</div>
@endsection
