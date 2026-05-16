<?php
/**
 * Trace 早期引导文件 - v6
 *
 * 零外部依赖，全部逻辑内联。保证在 Laravel 之前注册所有处理器。
 * 支持 CLI 纯文本、HTTP HTML、Ajax JSON 三种输出模式。
 */

$_trace_id = uniqid('tr_', true);
$_trace_last = null;
$_trace_crit = false;
$_trace_child = false;

/* ── 工具函数 ── */
function _t_clean(): void { $l=ob_get_level();for($i=0;$i<$l&&$i<20;$i++)@ob_end_clean(); }
function _t_bytes(int $b): string {
  if($b<=0)return'0B';
  $u=['B','KB','MB','GB','TB'];$i=min((int)floor(log($b,1024)),4);
  return round($b/(1024**$i),2).' '.$u[$i];
}
function _t_mem(): string {
  try{$u=memory_get_usage(true);$l=ini_get('memory_limit');
  if($l==='-1'||$l==='')return _t_bytes($u).'/无限制';
  $l=trim(strtoupper($l));$v=(int)$l;$c=substr($l,-1);
  $lb=match($c){'G'=>$v*1073741824,'M'=>$v*1048576,'K'=>$v*1024,default=>$v>0?$v:0};
  return $lb>0?_t_bytes($u).'/'. _t_bytes($lb).' ('.round($u/$lb*100,1).'%)':_t_bytes($u).'/无限制';
  }catch(\Throwable){return '?';}
}
function _t_dbg(): bool {
  $d=$_ENV['APP_DEBUG']??$_SERVER['APP_DEBUG']??getenv('APP_DEBUG')??false;
  return is_string($d)?in_array(strtolower($d),['true','1','yes','on'],true):(bool)$d;
}
function _t_fatal(int $t): bool { return in_array($t,[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR],true); }
function _t_names(int $n): string {
  static $m=[E_ERROR=>'FATAL',E_WARNING=>'WARN',E_PARSE=>'PARSE',E_NOTICE=>'NOTICE',
    E_CORE_ERROR=>'CORE_ERR',E_CORE_WARNING=>'CORE_WARN',E_COMPILE_ERROR=>'COMPILE_ERR',
    E_COMPILE_WARNING=>'COMPILE_WARN',E_USER_ERROR=>'USER_ERR',E_USER_WARNING=>'USER_WARN',
    E_USER_NOTICE=>'USER_NOTICE',E_RECOVERABLE_ERROR=>'RECOVERABLE',E_DEPRECATED=>'DEPRECATED',
    E_USER_DEPRECATED=>'USER_DEP'];
  return $m[$n]??'UNKNOWN';
}
function _t_crit(string $m): bool {
  static $p=null;
  if($p===null)$p=['curl_multi_add_handle','curl_multi_','curl_share_',
    'unable to create socket','couldn\'t create socket','too many open','too many files',
    'resource temporarily unavailable','failed to open stream: too many','failed to create',
    'pcntl_fork','apcu_add','apc_add','opcache','mmap failed',
    'socket_create','socket_bind','socket_listen','socket_connect',
    'disk quota exceeded','no space left','connection pool exhausted','connection refused',
    'maximum number of','limit reached','exhausted','cache full','cache was full'];
  $l=mb_strtolower($m);
  foreach($p as $v){if(str_contains($l,$v))return true;}
  return false;
}
function _t_is_ajax(): bool {
  return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])==='xmlhttprequest')
      || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'],'application/json'));
}

/* ═══ 应急渲染（零依赖）═══ */
function _t_render(int $code, string $badge, string $msg, string $sug='', string $file='', int $line=0): void {
  global $_trace_id;
  _t_clean();

  // ★ CLI 模式：纯文本 + ANSI 颜色
  if (PHP_SAPI === 'cli') {
    $mem = '';
    try { $u=memory_get_usage(true);$mem=' | Memory: '._t_bytes($u); } catch(\Throwable){}
    $isD = _t_dbg();
    echo "\n";
    echo "  \033[1;31m╔══════════════════════════════════════╗\033[0m\n";
    echo "  \033[1;31m║     Trace Error Intercepted          ║\033[0m\n";
    echo "  \033[1;31m╚══════════════════════════════════════╝\033[0m\n";
    echo "  \033[1;33m  [{$code}] {$badge}\033[0m\n";
    echo "  \033[0;37m  Message:\033[0m {$msg}\n";
    if (!empty($sug)) echo "  \033[0;36m  Suggestion:\033[0m {$sug}\n";
    if ($isD && !empty($file)) echo "  \033[0;37m  File:\033[0m {$file}:{$line}\n";
    echo "  \033[0;37m  PHP:\033[0m " . PHP_VERSION . "{$mem}\n";
    echo "  \033[0;37m  ID:\033[0m {$_trace_id}\n";
    echo "  \033[0;37m  Time:\033[0m " . date('Y-m-d H:i:s') . "\n\n";
    return;
  }

  // ★ Ajax 模式：JSON
  if (_t_is_ajax()) {
    if(!headers_sent()){http_response_code($code);header('Content-Type:application/json;charset=utf-8');}
    $resp = ['code'=>$code,'message'=>$msg,'intercepted'=>true];
    if (_t_dbg()) {
      $resp['debug'] = ['type'=>$badge,'file'=>$file,'line'=>$line,'suggestion'=>$sug];
    }
    echo json_encode($resp, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    return;
  }

  // ★ HTTP 模式：HTML
  if(!headers_sent()){http_response_code($code);header('Content-Type:text/html;charset=utf-8');}
  $b=htmlspecialchars($badge,ENT_QUOTES|ENT_HTML5,'UTF-8');
  $m=htmlspecialchars($msg,ENT_QUOTES|ENT_HTML5,'UTF-8');
  $s=htmlspecialchars($sug,ENT_QUOTES|ENT_HTML5,'UTF-8');
  $isD=_t_dbg();
  $detail='';
  if($isD) $detail='<div class="d"><strong>文件：</strong>'.htmlspecialchars($file,ENT_QUOTES|ENT_HTML5,'UTF-8').':'.$line.'<br><strong>内存：</strong>'.htmlspecialchars(_t_mem(),ENT_QUOTES|ENT_HTML5,'UTF-8').'<br><strong>PHP：</strong>'.PHP_VERSION.'</div>';
  $sugHtml=$s?'<div class="sg">💡 '.$s.'</div>':'';
  $cu = $_ENV['TRACE_CONTACT_URL'] ?? $_SERVER['TRACE_CONTACT_URL'] ?? '';
  $contactBtn = '';
  if (!empty($cu)) {
    $cuSafe = htmlspecialchars($cu, ENT_QUOTES|ENT_HTML5, 'UTF-8');
    $contactBtn = '<a href="'.$cuSafe.'" class="b2" target="_blank"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>联系我们</a>';
  }
  echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>'.$b.' - '.$code.'</title><style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:linear-gradient(135deg,#0f0c29,#302b63,#24243e);background-size:400% 400%;animation:bg 15s ease infinite;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
@keyframes bg{0%{background-position:0 50%}25%{background-position:100% 0}50%{background-position:100% 100%}75%{background-position:0 100%}100%{background-position:0 50%}}
.c{background:rgba(255,255,255,0.06);backdrop-filter:blur(24px);border-radius:28px;border:1px solid rgba(255,255,255,0.1);box-shadow:0 30px 80px rgba(0,0,0,0.5);max-width:680px;width:100%;padding:40px 35px;text-align:center;animation:en .7s cubic-bezier(.16,1,.3,1)}
@keyframes en{from{opacity:0;transform:translateY(40px) scale(.95)}to{opacity:1;transform:translateY(0) scale(1)}}
.cc,.sg,.d,.t,.b,.b2,a{all:unset}
.s{font-size:80px;font-weight:800;line-height:1;margin-bottom:6px;background:linear-gradient(135deg,#fa709a,#fee140);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;animation:pl 3s ease-in-out infinite}
@keyframes pl{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.85;transform:scale(.96)}}
.g{display:inline-block;padding:4px 14px;border-radius:20px;font-size:11px;font-weight:600;letter-spacing:.5px;margin-bottom:12px;background:rgba(102,126,234,0.2);color:#7f9cf5;border:1px solid rgba(102,126,234,0.3)}
h1{font-size:22px;color:#fff;margin-bottom:10px;font-weight:700}
.p{font-size:14px;color:rgba(255,255,255,.7);margin-bottom:20px;line-height:1.7}
.sg{font-size:13px;color:#68d391;background:rgba(104,211,145,.08);padding:12px 16px;margin-bottom:15px;text-align:left;border-left:3px solid #68d391;white-space:pre-line;line-height:1.6}
.d{font-size:12px;color:rgba(255,255,255,.4);margin-bottom:15px;padding:10px 14px;background:rgba(0,0,0,.25);border-radius:12px;text-align:left;line-height:1.7}
.d strong{color:rgba(255,255,255,.5)}
.b,.b2{display:inline-flex;padding:12px 28px;border-radius:14px;text-decoration:none;font-size:14px;font-weight:600;transition:all .3s;cursor:pointer;align-items:center;gap:8px}
.b{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;box-shadow:0 4px 20px rgba(102,126,234,.4)}
.b:hover{transform:translateY(-3px);box-shadow:0 8px 30px rgba(102,126,234,.55)}
.b2{background:rgba(255,255,255,.08);color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.1)}
.b2:hover{background:rgba(255,255,255,.14);transform:translateY(-2px)}
.t{margin-top:16px;font-size:11px;color:rgba(255,255,255,.25)}
</style></head><body><div class="c">
<div class="s">'.$code.'</div>
<div class="g">'.$b.'</div>
<h1>系统错误</h1>
<p class="p">'.$m.'</p>'.$sugHtml.$detail.'
<a href="/" class="b">返回首页</a>'.$contactBtn.'
<div class="t">ID: '.htmlspecialchars($_trace_id,ENT_QUOTES|ENT_HTML5,'UTF-8').' | '.date('Y-m-d H:i:s').'</div>
</div></body></html>';
}

/* ═══ 错误处理器 ═══ */
set_error_handler(function(int $s, string $m, string $f, int $l) {
  global $_trace_last, $_trace_crit, $_trace_child, $_trace_id;
  if(in_array($s,[E_NOTICE,E_USER_NOTICE,E_DEPRECATED,E_USER_DEPRECATED,E_STRICT],true))return false;
  $c=_t_crit($m);
  if($_trace_crit&&!$c)return false;
  $_trace_last=['type'=>_t_names($s),'code'=>$s,'message'=>$m,'file'=>$f,'line'=>$l,'time'=>date('Y-m-d H:i:s'),'eid'=>$_trace_id,'critical'=>$c];
  if($c) $_trace_crit=true;
  // ★ 不在此处渲染/exit，只标记关键警告并返回 true 阻止 PHP 默认输出
  // 渲染由 FallbackExceptionHandler(实际注册的处理器)或 shutdown 统一执行
  return $c;
});

/* ═══ 异常处理器 ═══ */
set_exception_handler(function(\Throwable $e) {
  global $_trace_last, $_trace_child, $_trace_id;
  $_trace_last=['type'=>get_class($e),'code'=>$e->getCode()?:500,'message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'time'=>date('Y-m-d H:i:s'),'eid'=>$_trace_id];
  if(!$_trace_child){_t_render(500,get_class($e),$e->getMessage(),'请检查代码逻辑',$e->getFile(),$e->getLine());exit(1);}
});

/* ═══ Shutdown + 内存预留 ═══ */
$_trace_mem = str_repeat('x', 192 * 1024);
register_shutdown_function(function() use (&$_trace_mem) {
  global $_trace_last, $_trace_crit, $_trace_id;
  $_trace_mem = '';
  $e = error_get_last();

  // ★ 关键修复：一旦检测到错误并渲染，立即 exit(1) 阻止后续所有输出
  // 防止 PHP/Laravel 的默认错误显示与 Trace 的拦截页面重叠

  // 1) 关键警告（资源耗尽）
  if ($_trace_crit && $_trace_last !== null) {
    $d = $_trace_last;
    _t_render(500, 'RESOURCE_EXHAUSTED', $d['message'] ?? '资源耗尽', '请优化资源使用', $d['file'] ?? '', $d['line'] ?? 0);
    exit(1);
  }

  // 2) 已拦截的异常
  if ($_trace_last !== null && !empty($_trace_last['type'])) {
    $d = $_trace_last;
    _t_render(500, $d['type'], $d['message'] ?? '错误', '请检查代码', $d['file'] ?? '', $d['line'] ?? 0);
    exit(1);
  }

  // 3) 致命错误（内存耗尽/超时/编译错误）
  if ($e !== null && _t_fatal($e['type'])) {
    _t_render(
      match($e['type']) { E_ERROR => 507, default => 500 },
      _t_names($e['type']),
      $e['message'] ?? '致命错误',
      '请检查错误日志',
      $e['file'] ?? '',
      $e['line'] ?? 0
    );
    exit(1);
  }
});

/* ═══ 进程隔离 ═══ */
if(PHP_SAPI!=='cli'&&PHP_OS_FAMILY!=='Windows'
  &&function_exists('pcntl_fork')&&function_exists('pcntl_waitpid')
  &&function_exists('pcntl_wifsignaled')&&function_exists('pcntl_wtermsig')
  &&function_exists('posix_kill')) {
  $pid=@pcntl_fork();
  if($pid===0){global $_trace_child;$_trace_child=true;}
  elseif($pid>0){
    $t=120;$s=time();$st=null;$r=0;
    $tf=sys_get_temp_dir().'/ts_'.$_trace_id.'.json';
    while(time()-$s<$t){$r=@pcntl_waitpid($pid,$st,WNOHANG);if($r===-1||$r>0)break;usleep(100000);}
    if($r===0){@posix_kill($pid,SIGKILL);@pcntl_waitpid($pid,$st,0);_t_render(504,'PROCESS_TIMEOUT','脚本超时(120s): 深层嵌套(scenario_33)或无限递归(scenario_35)',"1) 减少嵌套\n2) 检查递归终止条件\n3) 增加 memory_limit");exit(1);}
    $cd=[];
    if(file_exists($tf)){$d=@file_get_contents($tf);@unlink($tf);if(!empty($d))$cd=json_decode($d,true)?:[];}
    if(pcntl_wifsignaled($st)){
      $sig=pcntl_wtermsig($st);$_S=[];
      $_S[11]=['code'=>500,'type'=>'SEGFAULT','msg'=>'SIGSEGV: 深层嵌套(scenario_33)或无限递归(scenario_35)','sug'=>"1) 减少嵌套层级\n2) 检查递归终止\n3) 增加 memory_limit"];
      $_S[6]=['code'=>500,'type'=>'ABORTED','msg'=>'SIGABRT: 内存耗尽','sug'=>"1) 增加 memory_limit\n2) 优化内存\n3) 检查扩展泄漏"];
      $_S[9]=['code'=>502,'type'=>'OOM_KILL','msg'=>'SIGKILL: OOM Killer 杀死','sug'=>"1) 增加 memory_limit\n2) 减少大数组\n3) 检查泄漏"];
      $_S[15]=['code'=>502,'type'=>'TERMED','msg'=>'SIGTERM: 进程终止','sug'=>'检查长耗时操作'];
      $inf=$_S[$sig]??['code'=>500,'type'=>"SIG{$sig}",'msg'=>"信号 {$sig} 终止",'sug'=>'检查日志'];
      if(!empty($cd['message']))$inf['msg']=$cd['message'];
      _t_render($inf['code'],$inf['type'],$inf['msg'],$inf['sug'],$cd['file']??'',$cd['line']??0);
      exit(1);
    }
    if(pcntl_wifexited($st)){$ec=pcntl_wexitstatus($st);if($ec!==0&&empty($cd)){_t_render(500,'EXIT_ERROR',"退出码 {$ec}",'请检查日志');exit(1);}}
    exit(0);
  }
}

/* 加载 BootErrorHandler 补充（Blade 渲染） */
if(class_exists('\zxf\Trace\BootErrorHandler')){
  \zxf\Trace\BootErrorHandler::initFromBootstrap($_trace_id,$_trace_last,$_trace_crit,$_trace_child);
}