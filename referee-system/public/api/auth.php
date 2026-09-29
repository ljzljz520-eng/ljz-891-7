<?php
/**
 * 管理员鉴权接口
 *   POST api/auth.php?action=login   {username, password}
 *   POST api/auth.php?action=logout  (CSRF)
 *   GET  api/auth.php?action=me
 */

require_once __DIR__ . '/../../app/bootstrap.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_fail('请求方法不允许', 405);
        }
        $body     = request_body();
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($username === '' || $password === '') {
            json_fail('请输入账号和密码');
        }

        $stmt = db()->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            json_fail('账号或密码错误', 401);
        }

        login_current((int) $admin['id'], $admin['username']);
        json_ok([
            'admin'       => ['id' => (int) $admin['id'], 'username' => $admin['username']],
            'csrf_token'  => csrf_token(),
        ]);

    case 'logout':
        require_admin_api();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_fail('请求方法不允许', 405);
        }
        verify_csrf();
        logout_current();
        json_ok(['message' => '已退出登录']);

    case 'me':
        $admin = current_admin();
        if (!$admin) {
            json_fail('未登录', 401);
        }
        json_ok(['admin' => $admin, 'csrf_token' => csrf_token()]);

    default:
        json_fail('未知操作', 404);
}
