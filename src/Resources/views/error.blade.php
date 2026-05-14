<!DOCTYPE html><html lang="zh-CN"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>{{ $title ?? 'Error' }} - {{ $code ?? 500 }}</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%;overflow:hidden}
.trace-error-page{
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
  min-height:100vh;display:flex;align-items:center;justify-content:center;
  padding:20px;position:relative;
  background:linear-gradient(135deg,#0c0c28,#1a1450,#0f1a3e,#060618);
  background-size:400% 400%;animation:bgA 20s ease infinite;
}
@keyframes bgA{0%{background-position:0% 50%}25%{background-position:100% 0%}50%{background-position:100% 100%}75%{background-position:0% 100%}100%{background-position:0% 50%}}

/* 光晕 – 仅 CSS，不溢出 */
.trace-error-page .gl{position:fixed;border-radius:50%;filter:blur(100px);pointer-events:none}
.trace-error-page .g1{width:450px;height:450px;background:#667eea;opacity:0.2;top:-100px;right:-80px;animation:gd 12s ease-in-out infinite}
.trace-error-page .g2{width:400px;height:400px;background:#f093fb;opacity:0.16;bottom:-100px;left:-80px;animation:gd 15s ease-in-out infinite reverse}
@keyframes gd{0%,100%{transform:translate(0,0)}50%{transform:translate(40px,-30px)}}

/* 星星 – 纯 CSS，不溢出 */
.trace-error-page .st{position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none}
.trace-error-page .st i{position:absolute;width:2px;height:2px;background:#fff;border-radius:50%;animation:tw 2s ease-in-out infinite}
@keyframes tw{0%,100%{opacity:0.2;transform:scale(1)}50%{opacity:1;transform:scale(2)}}

/* 气泡 – 纯 CSS，不溢出 */
.trace-error-page .bb{position:fixed;border-radius:50%;pointer-events:none;z-index:0;
  background:radial-gradient(circle at 30% 30%,rgba(255,255,255,0.1),transparent);
  border:1px solid rgba(255,255,255,0.05);animation:bU linear infinite}
.trace-error-page .b1{width:120px;height:120px;left:5%;bottom:-60px;animation-duration:16s;animation-delay:0s}
.trace-error-page .b2{width:80px;height:80px;left:20%;bottom:-40px;animation-duration:20s;animation-delay:2s}
.trace-error-page .b3{width:150px;height:150px;left:40%;bottom:-75px;animation-duration:22s;animation-delay:4s}
.trace-error-page .b4{width:60px;height:60px;left:60%;bottom:-30px;animation-duration:14s;animation-delay:1s}
.trace-error-page .b5{width:100px;height:100px;left:78%;bottom:-50px;animation-duration:18s;animation-delay:3s}
@keyframes bU{0%{transform:translateY(0) scale(1);opacity:0.6}100%{transform:translateY(-110vh) scale(0.3);opacity:0}}

/* 卡片 */
.trace-error-page .bx{
  position:relative;z-index:10;max-width:680px;width:100%;padding:50px 45px;text-align:center;
  background:rgba(255,255,255,0.06);backdrop-filter:blur(30px);-webkit-backdrop-filter:blur(30px);
  border-radius:30px;border:1px solid rgba(255,255,255,0.1);
  box-shadow:0 30px 80px rgba(0,0,0,0.5);
  animation:bI .8s cubic-bezier(.16,1,.3,1);
}
@keyframes bI{from{opacity:0;transform:translateY(40px) scale(0.95)}to{opacity:1;transform:translateY(0) scale(1)}}

/* 状态码 */
.trace-error-page .cd{font-size:120px;font-weight:800;line-height:1;margin-bottom:8px;display:inline-block;position:relative;animation:cp 3s ease-in-out infinite}
.trace-error-page .cd.e4{background:linear-gradient(135deg,#f093fb,#f5576c);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.trace-error-page .cd.e5{background:linear-gradient(135deg,#fa709a,#fee140);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.trace-error-page .cd.e3{background:linear-gradient(135deg,#4facfe,#00f2fe);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
@keyframes cp{0%,100%{opacity:1;transform:scale(1)}50%{opacity:0.85;transform:scale(0.96)}}

/* 标签 */
.trace-error-page .bg{display:inline-block;padding:4px 16px;border-radius:20px;font-size:11px;font-weight:700;
  letter-spacing:.5px;text-transform:uppercase;margin-bottom:12px;animation:fu .6s ease-out .2s both}
.trace-error-page .bg.mm{background:rgba(229,62,62,0.2);color:#fc8181;border:1px solid rgba(229,62,62,0.25)}
.trace-error-page .bg.tt{background:rgba(237,137,54,0.2);color:#f6ad55;border:1px solid rgba(237,137,54,0.25)}
.trace-error-page .bg.ty{background:rgba(159,122,234,0.2);color:#b794f4;border:1px solid rgba(159,122,234,0.25)}
.trace-error-page .bg.db{background:rgba(72,187,120,0.2);color:#68d391;border:1px solid rgba(72,187,120,0.25)}
.trace-error-page .bg.nw{background:rgba(237,100,166,0.2);color:#f687b3;border:1px solid rgba(237,100,166,0.25)}
.trace-error-page .bg.df{background:rgba(102,126,234,0.2);color:#7f9cf5;border:1px solid rgba(102,126,234,0.25)}
@keyframes fu{from{opacity:0;transform:translateY(15px)}to{opacity:1;transform:translateY(0)}}

.trace-error-page h1{font-size:28px;color:#fff;margin-bottom:10px;font-weight:700;animation:fu .6s ease-out .15s both}
.trace-error-page .ms{font-size:15px;color:rgba(255,255,255,0.65);margin-bottom:28px;line-height:1.7;animation:fu .6s ease-out .25s both}

/* 按钮 */
.trace-error-page .ac{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;animation:fu .6s ease-out .35s both}
.trace-error-page .btn{padding:14px 30px;border-radius:14px;text-decoration:none;font-size:15px;font-weight:600;
  transition:all .3s;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:8px}
.trace-error-page .bp{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;box-shadow:0 4px 20px rgba(102,126,234,.35)}
.trace-error-page .bp:hover{transform:translateY(-3px);box-shadow:0 8px 30px rgba(102,126,234,.5)}
.trace-error-page .bs{background:rgba(255,255,255,.08);color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.1)}
.trace-error-page .bs:hover{background:rgba(255,255,255,.14);transform:translateY(-2px)}

/* 调试面板 */
.trace-error-page .db{margin-top:28px;text-align:left;border-radius:16px;overflow:hidden;border:1px solid rgba(255,255,255,.06);animation:fu .6s ease-out .5s both}
.trace-error-page .dh{background:rgba(102,126,234,.12);color:rgba(255,255,255,.85);padding:12px 20px;font-size:13px;font-weight:600;display:flex;align-items:center;justify-content:space-between;cursor:pointer;user-select:none;transition:background .3s}
.trace-error-page .dh:hover{background:rgba(102,126,234,.2)}
.trace-error-page .dh .to{transition:transform .3s}
.trace-error-page .dh.cl .to{transform:rotate(-90deg)}
.trace-error-page .db-b{padding:18px;background:rgba(0,0,0,.35);color:rgba(230,240,255,.85);font-family:Consolas,monospace;font-size:13px;line-height:1.6;transition:max-height .3s,opacity .3s;max-height:1500px;opacity:1;overflow:hidden}
.trace-error-page .db-b.hd{max-height:0;padding:0 18px;opacity:0}
.trace-error-page .di{margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid rgba(255,255,255,.05)}
.trace-error-page .di:last-child{margin:0;padding:0;border:none}
.trace-error-page .dl{color:rgba(160,174,192,.8);font-size:11px;text-transform:uppercase;letter-spacing:.6px;margin-bottom:3px}
.trace-error-page .dv{color:#68d391;word-break:break-word;font-size:13px}
.trace-error-page .dv pre{background:rgba(0,0,0,.3);padding:12px;border-radius:10px;overflow:auto;margin-top:6px;font-size:12px;line-height:1.5;max-height:300px}

/* 元信息 */
.trace-error-page .mt{margin-top:22px;padding-top:18px;border-top:1px solid rgba(255,255,255,.06);font-size:12px;color:rgba(255,255,255,.25);display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;animation:fu .6s ease-out .55s both}
.trace-error-page .mt span{display:inline-flex;align-items:center;gap:5px}
@media(max-width:480px){.trace-error-page .bx{padding:30px 18px}.trace-error-page .cd{font-size:80px}.trace-error-page h1{font-size:22px}.trace-error-page .ac{flex-direction:column}.trace-error-page .btn{width:100%;justify-content:center}}
</style></head><body>
<div class="trace-error-page">
  <div class="gl g1"></div><div class="gl g2"></div>
  <div class="st" id="stars"></div>
  <div class="bb b1"></div><div class="bb b2"></div><div class="bb b3"></div><div class="bb b4"></div><div class="bb b5"></div>

  <div class="bx">
    @php
    $cv=$code??500;$cc=$cv>=500?'e5':($cv>=400?'e4':'e3');
    $tv=$title??(isset($exception)?class_basename(get_class($exception)):'Error');
    $mv=$message??'处理请求时发生错误';
    $isDb=$isDebug??config('app.debug',false);
    $rid=$requestId??substr(md5(uniqid()),0,12);$ts=$timestamp??date('Y-m-d H:i:s');
    $cat=$category??'';
    $cls=match(true){
      str_contains($cat,'MEMORY')||str_contains($cat,'EXHAUSTED')||str_contains($cat,'RECURSION')||str_contains($cat,'NESTING')=>'mm',
      str_contains($cat,'TIMEOUT')||str_contains($cat,'TIME')=>'tt',
      str_contains($cat,'TYPE')||str_contains($cat,'VALUE')||str_contains($cat,'DIVISION')||str_contains($cat,'ARITHMETIC')||str_contains($cat,'MATCH')=>'ty',
      str_contains($cat,'DATABASE')||str_contains($cat,'SQL')=>'db',
      str_contains($cat,'HTTP')||str_contains($cat,'SOCKET')||str_contains($cat,'CURL')||str_contains($cat,'NETWORK')=>'nw',
      default=>'df'};
    @endphp
    <div class="cd {{$cc}}">{{$cv}}</div>
    @if(!empty($cat))<div class="bg {{$cls}}">{{$cat}}</div>@endif
    <h1>{{$tv}}</h1>
    <p class="ms">{{$mv}}</p>
    <div class="ac">
      <a href="{{url('/')}}" class="btn bp"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>返回首页</a>
      <button onclick="location.reload()" class="btn bs"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>刷新</button>
    </div>

    @if($isDb && isset($list) && is_array($list))
    <div class="db">
      <div class="dh" onclick="tog(this)"><span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg> 调试信息</span><span class="to">▼</span></div>
      <div class="db-b">@foreach($list as $it)<div class="di"><div class="dl">{{$it['label']??''}}</div><div class="dv">@if(($it['type']??'')==='code_html')<pre>{!!$it['value']??''!!}</pre>@elseif(($it['type']??'')==='code')<pre><code>{{$it['value']??''}}</code></pre>@elseif(($it['type']??'')==='debug_file')@php $e=$it['editor']??config('trace.editor','phpstorm');$f=$it['file']??'';$l=$it['line']??1;@endphp<a href="{{$e}}://open?file={{urlencode($f)}}&line={{$l}}" style="color:#68d391;text-decoration:underline;">{{$it['value']??$f}}</a>@else{{is_string($it['value']??'')?$it['value']:json_encode($it['value'],JSON_UNESCAPED_UNICODE)}}@endif</div></div>@endforeach</div>
    </div>
    @endif

    @if($isDb && isset($exception) && is_object($exception))
    <div class="db" style="margin-top:16px">
      <div class="dh" onclick="tog(this)"><span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg> 异常详情</span><span class="to">▼</span></div>
      <div class="db-b">
        <div class="di"><div class="dl">异常类</div><div class="dv">{{get_class($exception)}}</div></div>
        <div class="di"><div class="dl">文件</div><div class="dv" style="color:#f687b3">{{$exception->getFile()}}:{{$exception->getLine()}}</div></div>
        <div class="di"><div class="dl">堆栈 <span style="color:rgba(255,255,255,.3)">(仅调试)</span></div><div class="dv"><pre>{{$exception->getTraceAsString()}}</pre></div></div>
      </div>
    </div>
    @endif

    <div class="mt"><span><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>{{$ts}}</span><span><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.24 12.24a6 6 0 0 0-8.49-8.49L5 10.5V19h8.5z"></path><line x1="16" y1="8" x2="2" y2="22"></line><line x1="17.5" y1="15" x2="9" y2="15"></line></svg>ID:{{$rid}}</span></div>
  </div>
</div>
<script>
(function(){
  var c=document.getElementById('stars'),i,s;
  if(!c)return;
  for(i=0;i<60;i++){
    s=document.createElement('i');
    s.style.left=Math.random()*100+'%';s.style.top=Math.random()*100+'%';
    s.style.animationDelay=(Math.random()*5)+'s';
    s.style.animationDuration=(1.5+Math.random()*3)+'s';
    s.style.width=s.style.height=(1.5+Math.random()*3)+'px';
    c.appendChild(s);
  }
})();
function tog(h){h.classList.toggle('cl');var n=h.nextElementSibling;if(n)n.classList.toggle('hd')}
</script>
</body></html>
