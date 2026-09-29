<?php
/**
 * 会话、登录鉴权与 CSRF 校验
 */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('REFEREE_SID');
    session_start();

    // 闲置超时
    if (isset($_SESSION['last_active'])
        && time() - (int) $_SESSION['last_active'] > SESSION_LIFETIME) {
        logout_current();
    }
    $_SESSION['last_active'] = time();
}

function login_current(int $adminId, string $username): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['admin_id']  = $adminId;
    $_SESSION['admin_user'] = $username;
    $_SESSION['last_active'] = time();
}

function logout_current(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** 当前登录管理员；未登录返回 null */
function current_admin(): ?array
{
    start_session();
    if (!empty($_SESSION['admin_id'])) {
        return [
            'id'       => (int) $_SESSION['admin_id'],
            'username' => $_SESSION['admin_user'] ?? '',
        ];
    }
    return null;
}

/** API 鉴权：未登录直接 401 */
function require_admin_api(): array
{
    $admin = current_admin();
    if (!$admin) {
        json_fail('未登录或登录已过期', 401);
    }
    return $admin;
}

/** 页面鉴权：未登录跳转登录页 */
function require_admin_page(): array
{
    $admin = current_admin();
    if (!$admin) {
        header('Location: /login.php');
        exit;
    }
    return $admin;
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** 校验 POST 请求的 CSRF token（支持 JSON 字段 / X-CSRF-Token 头） */
function verify_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!$token) {
        $body = request_body();
        $token = $body['csrf_token'] ?? null;
    }
    start_session();
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!is_string($token) || $expected === '' || !hash_equals($expected, $token)) {
        json_fail('安全校验失败，请刷新页面后重试', 419);
    }
}
