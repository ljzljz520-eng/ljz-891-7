<?php
/**
 * 权限校验（PHP 端负责）
 * - require_login() 后台所有受保护页面必须调用
 * - 登录 / 登出 / CSRF 均在服务端完成
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function is_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

/** 后台门禁：未登录直接跳转到登录页（携带回跳地址） */
function require_login(): void
{
    if (!is_logged_in()) {
        $back = urlencode(basename($_SERVER['SCRIPT_NAME']) .
            (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
        redirect('login.php?back=' . $back);
    }
}

/** 动作型脚本门禁：未登录返回 403（避免跳转造成语义混乱） */
function require_login_ajax(): void
{
    if (!is_logged_in()) {
        http_response_code(403);
        exit('未登录或会话已失效，请重新登录后再操作。');
    }
}

function current_admin(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'       => $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'] ?? '',
    ];
}

function do_login(string $username, string $password): bool
{
    require_once __DIR__ . '/db.php';

    $admin = get_admin_by_username($username);
    // 即使用户不存在也执行一次哈希比较，缓解用户名枚举时序差异
    if ($admin === null) {
        password_verify($password, '$2y$10$aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        return false;
    }
    if (!password_verify($password, $admin['password_hash'])) {
        return false;
    }

    // 防会话固定
    session_regenerate_id(true);
    $_SESSION['admin_id']       = (int)$admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    return true;
}

function do_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
