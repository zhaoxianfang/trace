<?php
/**
 * PHP 8.2+ 和 Laravel 11+ 异常模拟工具
 * 包含 40 种异常场景，用于验证 Trace 包的拦截能力
 * 警告：仅在开发环境使用！
 *
 * 设计原则：
 * 1. 本文件仅提供异常触发场景，不处理任何拦截逻辑
 * 2. 所有异常拦截和处理均由 zxf/trace 包内处理器负责
 * 3. 每个场景函数保持最简洁，只保留核心异常触发代码
 */

$scenarios = [
    '1'  => 'PHP 8.2 - 动态属性弃用',
    '2'  => 'PHP 8.2 - ${} 字符串插值弃用',
    '3'  => 'PHP 8.2 - 类型不匹配',
    '4'  => 'PHP 8.3 - 枚举常量类型',
    '5'  => 'PHP 8.4 - 隐式 nullable 弃用',
    '6'  => '执行超时',
    '7'  => '数据库查询超时',
    '8'  => 'HTTP 请求超时',
    '9'  => 'Redis 超时',
    '10' => '队列任务超时',
    '11' => 'TypeError - 类型不匹配',
    '12' => 'ValueError - 值错误',
    '13' => 'ArithmeticError - 算术错误',
    '14' => 'DivisionByZeroError - 除零错误',
    '15' => 'UnhandledMatchError - 未处理匹配',
    '16' => 'Fiber 错误',
    '17' => 'Laravel 唯一性验证异常',
    '18' => 'Laravel 批量赋值异常',
    '19' => 'Laravel 关联关系内存爆炸',
    '20' => 'Laravel N+1 查询',
    '21' => 'Laravel 懒加载限制',
    '22' => 'Laravel 事件死循环',
    '23' => 'Laravel 中间件堆栈溢出',
    '24' => 'Laravel 门面 Mock 失败',
    '25' => 'Laravel 服务容器循环依赖',
    '26' => 'OpCache 内存耗尽',
    '27' => 'APCu 缓存耗尽',
    '28' => 'PCNTL 进程限制',
    '29' => 'Socket 连接耗尽',
    '30' => 'cURL 多句柄耗尽',
    '31' => '内存+CPU+超时组合',
    '32' => '顺序执行场景1-31',
    '33' => '深层嵌套数组内存溢出',
    '34' => 'JSON编码栈溢出',
    '35' => '无限递归函数调用',
    '36' => '对象循环引用序列化',
    '37' => '超大对象图遍历',
    '38' => 'XML/JSON解析炸弹',
    '39' => '顺序执行场景1-38',
];

/**
 * [场景1]: PHP 8.2 - 动态属性弃用
 */
function scenario_1() {
    $user = new \stdClass();
    $user->dynamicProperty = 'test';
}

/**
 * [场景2]: PHP 8.2 - ${} 字符串插值弃用
 */
function scenario_2() {
    $name = 'World';
    $str = "Hello ${name}";
}

/**
 * [场景3]: PHP 8.2 - 类型不匹配
 */
function scenario_3() {
    strlen([1, 2, 3]);
}

/**
 * [场景4]: PHP 8.3 - 枚举常量类型
 */
function scenario_4() {
    enum Status: string {
        case ACTIVE = 'active';
    }
    class Config {
        const DEFAULT = Status::ACTIVE;
    }
}

/**
 * [场景5]: PHP 8.4 - 隐式 nullable 弃用
 */
function scenario_5() {
    function testNullable(string $param = null) {
        return $param;
    }
    testNullable(null);
}

/**
 * [场景6]: 执行超时
 */
function scenario_6() {
    set_time_limit(2);
    while (true) {
        for ($i = 0; $i < 1000000; $i++) {
            sqrt($i);
        }
    }
}

/**
 * [场景7]: 数据库查询超时
 */
function scenario_7() {
    DB::statement('SET SESSION max_execution_time=3000');
    DB::select('SELECT SLEEP(5), 1');
}

/**
 * [场景8]: HTTP 请求超时
 */
function scenario_8() {
    $client = new Client(['timeout' => 1, 'connect_timeout' => 1]);
    $client->get('https://httpbin.org/delay/10');
}

/**
 * [场景9]: Redis 超时
 */
function scenario_9() {
    $redis = new \Redis();
    $redis->connect('127.0.0.1', 6379, 0.1);
    $redis->setOption(\Redis::OPT_READ_TIMEOUT, 0.1);
    $redis->blpop('nonexistent_key', 5);
}

/**
 * [场景10]: 队列任务超时
 */
function scenario_10() {
    set_time_limit(3);
    while (true) {
        for ($i = 0; $i < 1000000; $i++) {
            sqrt($i);
        }
    }
}

/**
 * [场景11]: TypeError - 类型不匹配
 */
function scenario_11() {
    function expectsInt(int $v): int {
        return $v * 2;
    }
    expectsInt("not a number");
}

/**
 * [场景12]: ValueError - 值错误
 */
function scenario_12() {
    json_decode('{}', false, -1);
}

/**
 * [场景13]: ArithmeticError - 算术错误
 */
function scenario_13() {
    $result = 1 << 1000000;
}

/**
 * [场景14]: DivisionByZeroError - 除零错误
 */
function scenario_14() {
    intdiv(10, 0);
}

/**
 * [场景15]: UnhandledMatchError - 未处理匹配
 */
function scenario_15() {
    $value = 5;
    match ($value) {
        1 => 'one',
        2 => 'two',
        3 => 'three',
    };
}

/**
 * [场景16]: Fiber 错误
 */
function scenario_16() {
    $fiber = new \Fiber(function () {
        throw new \Exception("Fiber 内部异常");
    });
    $fiber->start();
}

/**
 * [场景17]: Laravel 唯一性验证异常
 */
function scenario_17() {
    $validator = Validator::make(
        ['email' => 'test@example.com'],
        ['email' => 'unique:users,email']
    );
    if ($validator->fails()) {
        throw new \Illuminate\Validation\ValidationException($validator);
    }
}

/**
 * [场景18]: Laravel 批量赋值异常
 */
function scenario_18() {
    $user = new class extends Model {
        protected $table = 'users';
        protected $fillable = ['name'];
    };
    $user->fill(['name' => 'John', 'email' => 'john@example.com']);
}

/**
 * [场景19]: Laravel 关联关系内存爆炸
 */
function scenario_19() {
    $data = [];
    while (true) {
        $data[] = str_repeat('x', 1024 * 1024);
    }
}

/**
 * [场景20]: Laravel N+1 查询
 */
function scenario_20() {
    DB::statement('CREATE TEMPORARY TABLE IF NOT EXISTS t_posts (id INT, user_id INT)');
    DB::statement('CREATE TEMPORARY TABLE IF NOT EXISTS t_users (id INT, name VARCHAR(100))');
    for ($i = 1; $i <= 100; $i++) {
        DB::table('t_posts')->insert(['id' => $i, 'user_id' => $i]);
        DB::table('t_users')->insert(['id' => $i, 'name' => "User {$i}"]);
    }
    $posts = DB::table('t_posts')->get();
    foreach ($posts as $post) {
        DB::table('t_users')->where('id', $post->user_id)->first();
    }
}

/**
 * [场景21]: Laravel 懒加载限制
 */
function scenario_21() {
    Model::preventLazyLoading(!app()->isProduction());
    $author = new class extends Model {
        protected $table = 'authors';
    };
    $author->books;
}

/**
 * [场景22]: Laravel 事件死循环
 */
function scenario_22() {
    $count = 0;
    Event::listen('trace.test.loop', function () use (&$count) {
        if (++$count > 100) {
            throw new \RuntimeException('事件死循环');
        }
        Event::dispatch('trace.test.loop');
    });
    Event::dispatch('trace.test.loop');
}

/**
 * [场景23]: Laravel 中间件堆栈溢出
 */
function scenario_23() {
    $app = app();
    $app->make(\Illuminate\Contracts\Http\Kernel::class)->handle(
        \Illuminate\Http\Request::create('/')
    );
}

/**
 * [场景24]: Laravel 门面 Mock 失败
 */
function scenario_24() {
    Cache::shouldReceive('get')->once()->andReturn('mocked');
}

/**
 * [场景25]: Laravel 服务容器循环依赖
 */
function scenario_25() {
    app()->bind('zxf.trace.a', fn($app) => $app->make('zxf.trace.b'));
    app()->bind('zxf.trace.b', fn($app) => $app->make('zxf.trace.a'));
    app()->make('zxf.trace.a');
}

/**
 * [场景26]: OpCache 内存耗尽
 */
function scenario_26() {
    for ($i = 0; $i < 10000; $i++) {
        $file = sys_get_temp_dir() . "/test_{$i}.php";
        file_put_contents($file, "<?php function test_{$i}() {{ return '{$i}'; }}");
        include_once $file;
    }
}

/**
 * [场景27]: APCu 缓存耗尽
 */
function scenario_27() {
    $i = 0;
    while (apcu_add("key_{$i}", str_repeat('x', 1024 * 1024))) {
        $i++;
    }
}

/**
 * [场景28]: PCNTL 进程限制
 */
function scenario_28() {
    for ($i = 0; $i < 1000; $i++) {
        $pid = pcntl_fork();
        if ($pid == -1) {
            break;
        } elseif ($pid == 0) {
            while (true) sleep(1);
            exit;
        }
    }
}

/**
 * [场景29]: Socket 连接耗尽
 */
function scenario_29() {
    $sockets = [];
    for ($i = 0; $i < 10000; $i++) {
        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            break;
        }
        $sockets[] = $socket;
    }
}

/**
 * [场景30]: cURL 多句柄耗尽
 */
function scenario_30() {
    $mh = curl_multi_init();
    for ($i = 0; $i < 1000; $i++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://httpbin.org/delay/{$i}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_multi_add_handle($mh, $ch);
    }
}

/**
 * [场景31]: 内存+CPU+超时组合
 */
function scenario_31() {
    set_time_limit(3);
    $data = [];
    while (true) {
        $data[] = str_repeat('x', 1024 * 100);
        for ($i = 0; $i < 10000; $i++) {
            sin($i) * cos($i);
        }
    }
}

/**
 * [场景32]: 顺序执行场景1-31
 */
function scenario_32() {
    for ($i = 1; $i <= 31; $i++) {
        $func = "scenario_{$i}";
        if (function_exists($func)) {
            $func();
        }
    }
}

/**
 * [场景33]: 深层嵌套数组导致内存溢出
 */
function scenario_33() {
    $deepArray = [];
    for ($i = 0; $i < 2000; $i++) {
        $deepArray = [$deepArray];
    }
}

/**
 * [场景34]: JSON 编码栈溢出
 */
function scenario_34() {
    $arr = [];
    for ($i = 0; $i < 2000; $i++) {
        $arr = ['level' => $i, 'next' => $arr];
    }
    json_encode($arr, JSON_PARTIAL_OUTPUT_ON_ERROR, 512);
}

/**
 * [场景35]: 无限递归函数调用
 */
function scenario_35() {
    function infiniteRecursion($n = 0) {
        return infiniteRecursion($n + 1);
    }
    infiniteRecursion();
}

/**
 * [场景36]: 对象循环引用序列化
 */
function scenario_36() {
    $a = new \stdClass();
    $b = new \stdClass();
    $a->child = $b;
    $b->parent = $a;
    serialize($a);
}

/**
 * [场景37]: 超大对象图遍历
 */
function scenario_37() {
    $root = new \stdClass();
    $current = $root;
    for ($i = 0; $i < 50000; $i++) {
        $current->next = new \stdClass();
        $current->next->data = str_repeat('x', 100);
        $current = $current->next;
    }
    $count = 0;
    $current = $root;
    while ($current !== null && isset($current->next)) {
        $current = $current->next;
        $count++;
    }
}

/**
 * [场景38]: XML/JSON 解析炸弹
 */
function scenario_38() {
    $json = '[';
    for ($i = 0; $i < 10000; $i++) {
        $json .= '[';
    }
    $json .= '1';
    for ($i = 0; $i < 10000; $i++) {
        $json .= ']';
    }
    $json .= ']';
    json_decode($json, true, 512);
}

/**
 * [场景39]: 顺序执行场景1-38
 */
function scenario_39() {
    for ($i = 1; $i <= 38; $i++) {
        $func = "scenario_{$i}";
        if (function_exists($func)) {
            $func();
        }
    }
}
