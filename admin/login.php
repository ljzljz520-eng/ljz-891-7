<?php
/**
 * 后台管理员登录
 * - 服务端校验账号密码（password_hash / password_verify）
 * - CSRF 防护；已登录直接进入后台
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

if (is_logged_in()) {
    redirect('index.php');
}

$back = (string)($_GET['back'] ?? 'index.php');
// 仅允许站内相对跳转，防开放重定向
if (!preg_match('/^[a-zA-Z0-9_\.\?=&\-]*$/', $back)) {
    $back = 'index.php';
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = '请输入账号和密码。';
    } elseif (do_login($username, $password)) {
        set_flash('success', '登录成功，欢迎回来。');
        redirect(str_contains($back, '.php') ? $back : 'index.php');
    } else {
        $error = '账号或密码错误，请重试。';
        header('X-Login-Fail: 1');
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<main class="container">
    <div class="login-wrap">
        <div class="login-card">
            <h1>🛡️ 管理员登录</h1>
            <p class="sub"><?= e(APP_NAME) ?> · 后台管理</p>

            <?php if ($f = get_flash()): ?>
                <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="login.php?back=<?= e(urlencode($back)) ?>" autocomplete="off">
                <?= csrf_field() ?>
                <div class="form-row">
                    <label for="username">管理员账号</label>
                    <input type="text" id="username" name="username" required autofocus
                           value="<?= e($_POST['username'] ?? '') ?>" placeholder="admin">
                </div>
                <div class="form-row">
                    <label for="password">登录密码</label>
                    <input type="password" id="password" name="password" required placeholder="请输入密码">
                </div>
                <button type="submit" class="btn btn-primary">登 录</button>
            </form>
        </div>
        <p style="text-align:center;color:#64748b;font-size:12.5px;margin-top:14px;">
            默认演示账号：admin / admin123（登录后请及时修改）
        </p>
    </div>
</main>
</body>
</html>
