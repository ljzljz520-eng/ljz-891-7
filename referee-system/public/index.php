<?php
require_once __DIR__ . '/../app/bootstrap.php';
// 公开页面，无需登录
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>赛事裁判资格查询系统</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="hero">
        <h1>赛事裁判资格查询系统</h1>
        <p>输入裁判编号与姓名，核验裁判级别、执裁项目、资格有效期及培训情况</p>
        <span class="badge-tag">官方资格信息 · 实时核验</span>
    </header>

    <main class="container-narrow">
        <section class="search-panel">
            <form class="search-form" id="queryForm" autocomplete="off">
                <div class="form-group">
                    <label for="refereeCode">裁判编号</label>
                    <input type="text" id="refereeCode" name="referee_code"
                           placeholder="如：RJ2023001" maxlength="30" required>
                </div>
                <div class="form-group">
                    <label for="refereeName">裁判姓名</label>
                    <input type="text" id="refereeName" name="name"
                           placeholder="如：张伟" maxlength="30" required>
                </div>
                <button type="submit" class="btn btn-primary">查询资格</button>
            </form>
            <div class="hint">
                编号与姓名需同时匹配才能查询 · 管理员请前往
                <a href="/admin.php">后台管理</a>
            </div>
        </section>

        <section class="result-area" id="resultArea"></section>
    </main>

    <script src="/assets/js/query.js"></script>
</body>
</html>
