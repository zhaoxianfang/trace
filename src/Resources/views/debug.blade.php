<!DOCTYPE html><html lang="zh-CN"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>{{ $title ?? 'Debug' }} - Trace Debugger</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%}
.trace-debug-page{
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
  min-height:100vh;padding:40px 20px;position:relative;
  background:linear-gradient(170deg,#070714,#0d0d24,#12103a,#080820);
}

/* 光晕 – fixed 不溢出 */
.trace-debug-page .gl{position:fixed;border-radius:50%;filter:blur(150px);pointer-events:none}
.trace-debug-page .g1{width:500px;height:500px;background:#667eea;opacity:0.12;top:-200px;left:-150px;animation:g1 15s ease-in-out infinite}
.trace-debug-page .g2{width:400px;height:400px;background:#f093fb;opacity:0.1;bottom:-150px;right:-100px;animation:g2 18s ease-in-out infinite reverse}
.trace-debug-page .g3{width:300px;height:300px;background:#4facfe;opacity:0.08;top:50%;left:50%;animation:g3 10s ease-in-out infinite}
@keyframes g1{0%,100%{transform:translate(0,0)}50%{transform:translate(40px,-40px)}}
@keyframes g2{0%,100%{transform:translate(0,0)}50%{transform:translate(-40px,40px)}}
@keyframes g3{0%,100%{transform:translate(-50%,-50%) scale(0.8);opacity:0.06}50%{transform:translate(-50%,-50%) scale(1.3);opacity:0.12}}

/* 网格 */
.trace-debug-page .gr{position:fixed;inset:0;pointer-events:none;
  background-image:linear-gradient(rgba(102,126,234,0.05) 1px,transparent 1px),linear-gradient(90deg,rgba(102,126,234,0.05) 1px,transparent 1px);
  background-size:50px 50px;animation:gm 20s linear infinite;
  mask-image:radial-gradient(ellipse 70% 70% at 50% 50%,black 30%,transparent 70%);
  -webkit-mask-image:radial-gradient(ellipse 70% 70% at 50% 50%,black 30%,transparent 70%);
}
@keyframes gm{0%{transform:translate(0,0)}100%{transform:translate(50px,50px)}}

/* 容器 */
.trace-debug-page .bx{position:relative;z-index:10;max-width:900px;margin:0 auto}

/* 头部 */
.trace-debug-page .hd{text-align:center;margin-bottom:35px;animation:fs .7s ease-out}
.trace-debug-page .ic{width:80px;height:80px;margin:0 auto 18px;
  background:linear-gradient(135deg,#667eea,#764ba2);border-radius:22px;
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 8px 30px rgba(102,126,234,.25);position:relative}
.trace-debug-page .ic::before{content:'';position:absolute;inset:-4px;border-radius:26px;
  background:linear-gradient(135deg,rgba(102,126,234,.4),rgba(240,147,251,.3));z-index:-1;animation:ip 3s ease-in-out infinite}
@keyframes ip{0%,100%{opacity:.5;transform:scale(1)}50%{opacity:.8;transform:scale(1.06)}}
.trace-debug-page .ic svg{width:36px;height:36px;color:#fff}
.trace-debug-page h1{font-size:30px;color:#fff;margin-bottom:6px;font-weight:700}
.trace-debug-page .sb{color:rgba(255,255,255,.4);font-size:14px;letter-spacing:2px}

/* 卡片 */
.trace-debug-page .cd{
  background:rgba(255,255,255,.035);backdrop-filter:blur(30px);-webkit-backdrop-filter:blur(30px);
  border-radius:26px;border:1px solid rgba(255,255,255,.06);
  overflow:hidden;animation:fs .7s ease-out .1s both;box-shadow:0 25px 70px rgba(0,0,0,.4);
}
@keyframes fs{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
.trace-debug-page .ch{background:linear-gradient(135deg,rgba(102,126,234,.1),rgba(118,75,162,.1));padding:18px 24px;border-bottom:1px solid rgba(255,255,255,.04)}
.trace-debug-page .ct{font-size:17px;color:#fff;font-weight:600;display:flex;align-items:center;gap:8px}

/* 列表 */
.trace-debug-page .ls{padding:10px}
.trace-debug-page .it{
  background:rgba(0,0,0,.14);border-radius:14px;padding:16px 20px;margin-bottom:8px;
  border:1px solid rgba(255,255,255,.03);transition:all .3s;
  animation:ii .5s ease-out both;display:flex;align-items:flex-start;gap:12px;
}
.trace-debug-page .it:hover{background:rgba(0,0,0,.25);border-color:rgba(102,126,234,.15);transform:translateX(5px)}
@keyframes ii{from{opacity:0;transform:translateY(15px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}

.trace-debug-page .ih{display:flex;align-items:center;gap:6px;flex-shrink:0;min-width:110px}
.trace-debug-page .tp{padding:2px 8px;border-radius:16px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.4px}
.trace-debug-page .tp.s{background:rgba(104,211,145,.15);color:#68d391}
.trace-debug-page .tp.c{background:rgba(246,135,179,.15);color:#f687b3}
.trace-debug-page .tp.d{background:rgba(102,126,234,.15);color:#7f9cf5}
.trace-debug-page .tp.h{background:rgba(250,112,154,.15);color:#fa709a}
.trace-debug-page .il{color:rgba(255,255,255,.75);font-weight:600;font-size:13px;white-space:nowrap}

.trace-debug-page .iv{color:rgba(255,255,255,.5);font-size:13px;line-height:1.6;word-break:break-word;flex:1}
.trace-debug-page .cb{background:rgba(0,0,0,.35);border-radius:10px;padding:14px;margin-top:5px;overflow:auto;border:1px solid rgba(255,255,255,.05)}
.trace-debug-page .cb pre{font-family:'JetBrains Mono',Consolas,monospace;font-size:12px;line-height:1.5;color:rgba(255,255,255,.85);margin:0}
.trace-debug-page .cb code{color:rgba(255,255,255,.85)}
.trace-debug-page .elc{background:rgba(248,81,73,.1);color:#f85149;display:block;border-left:3px solid #f85149;padding-left:12px;margin-left:-14px;font-weight:600}

.trace-debug-page .fl{display:inline-flex;align-items:center;gap:5px;color:#7f9cf5;text-decoration:none;
  padding:5px 12px;background:rgba(102,126,234,.08);border-radius:8px;font-size:13px;transition:all .3s}
.trace-debug-page .fl:hover{background:rgba(102,126,234,.18);color:#a78bfa;transform:translateY(-1px)}

/* 按钮 */
.trace-debug-page .ac{display:flex;gap:12px;justify-content:center;margin-top:30px;animation:fs .6s ease-out .4s both}
.trace-debug-page .btn{padding:12px 26px;border-radius:12px;text-decoration:none;font-size:14px;font-weight:600;
  transition:all .3s;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:8px}
.trace-debug-page .bp{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;box-shadow:0 4px 15px rgba(102,126,234,.3)}
.trace-debug-page .bp:hover{transform:translateY(-3px);box-shadow:0 8px 25px rgba(102,126,234,.5)}
.trace-debug-page .bs{background:rgba(255,255,255,.06);color:rgba(255,255,255,.6);border:1px solid rgba(255,255,255,.08)}
.trace-debug-page .bs:hover{background:rgba(255,255,255,.1);transform:translateY(-2px)}

/* 底栏 */
.trace-debug-page .mb{display:flex;justify-content:space-between;align-items:center;
  padding:12px 24px;background:rgba(0,0,0,.2);border-top:1px solid rgba(255,255,255,.04);font-size:12px;color:rgba(255,255,255,.25)}
.trace-debug-page .mb>span{display:flex;align-items:center;gap:5px}
@media(max-width:640px){
  .trace-debug-page{padding:20px 12px}.trace-debug-page h1{font-size:24px}
  .trace-debug-page .it{padding:12px}.trace-debug-page .ac{flex-direction:column}
  .trace-debug-page .btn{width:100%;justify-content:center}.trace-debug-page .mb{flex-direction:column;gap:6px;text-align:center}
}
</style></head><body>
<div class="trace-debug-page">
  <div class="gr"></div>
  <div class="gl g1"></div><div class="gl g2"></div><div class="gl g3"></div>

  <div class="bx">
    <div class="hd">
      <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg></div>
      <h1>{{ $title ?? 'Debug Information' }}</h1>
      <p class="sb">✦ Trace Debugger ✦</p>
    </div>

    <div class="cd">
      <div class="ch"><div class="ct"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>Debug Details</div></div>
      <div class="ls">
        @forelse($list ?? [] as $i => $it)
        @php $t=$it['type']??'s';$l=$it['label']??'Unknown';$v=$it['value']??'' @endphp
        <div class="it" style="animation-delay:{{0.04+$i*0.04}}s">
          <div class="ih"><span class="tp {{$t==='string'?'s':($t==='code'?'c':($t==='debug_file'?'d':'h'))}}">{{$t}}</span><span class="il">{{$l}}</span></div>
          <div class="iv">
            @if($t==='code_html')<div class="cb"><pre>{!!$v!!}</pre></div>
            @elseif($t==='code')<div class="cb"><pre><code>{{$v}}</code></pre></div>
            @elseif($t==='debug_file')@php $e=$it['editor']??config('trace.editor','phpstorm');$f=$it['file']??'';$l=$it['line']??1 @endphp
            <a href="{{$e}}://open?file={{urlencode($f)}}&line={{$l}}" class="fl"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>{{$v}}</a>
            @else<span>{{is_string($v)?$v:json_encode($v,JSON_UNESCAPED_UNICODE)}}</span>@endif
          </div>
        </div>
        @empty
        <div class="it" style="flex-direction:column;text-align:center;padding:40px 20px">
          <div style="color:rgba(255,255,255,.35);font-size:14px;margin-bottom:10px">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="margin-bottom:15px;opacity:.3"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
            <div>暂无调试内容</div>
          </div>
          <div style="color:rgba(255,255,255,.25);font-size:12px">使用 <code style="background:rgba(102,126,234,.15);color:#7f9cf5;padding:2px 6px;border-radius:4px;font-family:'JetBrains Mono',monospace">trace(mixed ...$args)</code> 调试</div>
        </div>
        @endforelse
      </div>
      <div class="mb"><span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>{{date('Y-m-d H:i:s')}}</span><span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.24 12.24a6 6 0 0 0-8.49-8.49L5 10.5V19h8.5z"></path></svg>Trace Debugger</span></div>
    </div>

    <div class="ac">
      @php $cu = config('trace.contact_url', ''); @endphp
      @if(!empty($cu))
      <a href="{{$cu}}" class="btn bs" target="_blank"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>联系我们</a>
      @endif
      <a href="{{url('/')}}" class="btn bp"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>首页</a>
      <button onclick="history.back()" class="btn bs"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"></path></svg>返回</button>
    </div>
  </div>
</div>
</body></html>
