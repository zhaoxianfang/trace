<?php
/**
 * Trace 包早期引导文件
 *
 * 在 Laravel 框架启动前通过 composer autoload.files 加载，
 * 确保 register_shutdown_function 等异常处理和注册 在 Laravel 之前抢先注册。
 *
 * 拦截范围：
 * - PHP 致命错误 (E_ERROR): 内存耗尽、无限递归 和 test_trace.php 中的其他异常情况等
 * - 未捕获异常
 * - 内存相关警告
 */
