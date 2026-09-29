<?php
require_once __DIR__ . '/../app/bootstrap.php';

// 已登录则直接进入后台
if (current_admin()) {
    header('Location: /admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 - 赛事裁判资格查询系统</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="login-wrap">
    <form class="login-card" id="loginForm" autocomplete="off">
        <h1>🛡️ 后台管理登录</h1>
        <p class="sub">赛事裁判资格管理平台</p>

        <div class="form-group">
            <label for="username">管理员账号</label>
            <input type="text" id="username" name="username" placeholder="请输入账号" required maxlength="30">
        </div>
        <div class="form-group">
            <label for="password">登录密码</label>
            <input type="password" id="password" name="password" placeholder="请输入密码" required maxlength="50">
        </div>

        <div class="alert alert-error" id="loginError" style="display:none;margin:0 0 12px"></div>

        <button type="submit" class="btn btn-primary" id="loginBtn">登 录</button>
        <a href="/" class="login-back">← 返回资格查询首页</a>
    </form>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = document.getElementById('loginBtn');
    const errBox = document.getElementById('loginError');
    errBox.style.display = 'none';

    const payload = {
        username: document.getElementById('username').value.trim(),
        password: document.getElementById('password').value
    };

    btn.disabled = true;
    btn.textContent = '登录中…';
    try {
        const resp = await fetch('/api/auth.php?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await resp.json();
        if (!resp.ok || !data.ok) {
            errBox.textContent = data.error || '登录失败';
            errBox.style.display = 'block';
        } else {
            window.location.href = '/admin.php';
        }
    } catch (err) {
        errBox.textContent = '网络异常，请稍后重试';
        errBox.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.textContent = '登 录';
    }
});
</script>
</body>
</html>
