<?php
/**
 * PHP 内置服务器路由（仅用于本地开发）
 *   php -S 127.0.0.1:8000 router.php
 * 作用：拦截对 data / includes 等敏感目录的直接访问
 */

declare(strict_types=1);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

// 敏感资源一律拒绝
if (preg_match('#^/(data|includes)(/|$)#', $uri) || str_ends_with($uri, '.db')) {
    http_response_code(403);
    exit('Forbidden');
}

// 其余静态资源交给内置服务器处理
$file = __DIR__ . $uri;
if ($uri !== '/' && is_file($file)) {
    return false;
}
return false;
