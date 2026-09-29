<?php
/**
 * 页面布局辅助：前台 / 后台页头页脚
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

function page_header(string $title, string $scope = 'public'): void
{
    $admin = $scope === 'admin' ? current_admin() : null;
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= $scope === 'admin' ? '../assets/style.css' : 'assets/style.css' ?>">
</head>
<body class="scope-<?= e($scope) ?>">
<header class="topbar">
    <div class="container topbar-inner">
        <a class="brand" href="<?= $scope === 'admin' ? 'index.php' : 'index.php' ?>">
            <span class="brand-mark">裁</span>
            <span><?= e(APP_NAME) ?></span>
        </a>
        <nav class="topnav">
            <?php if ($scope === 'admin' && $admin): ?>
                <a href="index.php">资格管理</a>
                <a href="edit.php">新增资格</a>
                <span class="who">管理员：<?= e($admin['username']) ?></span>
                <form method="post" action="logout.php" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost btn-sm">退出登录</button>
                </form>
            <?php else: ?>
                <a href="index.php">资格查询</a>
                <a href="admin/login.php">管理员入口</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container main">
    <?php if ($f = get_flash()): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
    <?php endif; ?>
<?php
}

function page_footer(): void
{
    ?>
</main>
<footer class="footer">
    <div class="container">
        <span>© <?= date('Y') ?> <?= e(APP_NAME) ?> · 裁判资格信息以发证单位登记为准</span>
    </div>
</footer>
</body>
</html>
<?php
}
