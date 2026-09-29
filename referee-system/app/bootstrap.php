<?php
/**
 * API / 页面统一引导
 */

date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/init.php';

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '0'); // 错误不直接打到响应体，由异常处理器接管
}

// 统一异常处理，保证输出始终为 JSON
set_exception_handler(function (Throwable $e): void {
    $message = DEBUG_MODE ? $e->getMessage() : '服务器内部错误';
    if (!headers_sent()) {
        json_fail($message, 500);
    }
});
