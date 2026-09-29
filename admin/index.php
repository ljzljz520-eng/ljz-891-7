<?php
/**
 * 后台：裁判资格列表
 * 权限：require_login()（PHP 端权限校验）
 * 功能：搜索 / 状态筛选 / 统计 / 进入新增·编辑·停用启用
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

require_login(); // 权限校验：未登录跳转登录页

$keyword = trim((string)($_GET['q'] ?? ''));
$status  = (string)($_GET['status'] ?? '');
if (!in_array($status, ['', 'active', 'revoked'], true)) {
    $status = '';
}

$rows  = list_referees($keyword, $status);
$all   = list_referees();
$stat  = ['total' => count($all), 'active' => 0, 'expired' => 0, 'revoked' => 0];
foreach ($all as $r) {
    $stat[referee_status($r)]++;
}

page_header('资格管理', 'admin');
?>

<div class="page-head">
    <h1>裁判资格管理</h1>
    <a href="edit.php" class="btn btn-primary">＋ 新增资格</a>
</div>

<div class="stat-grid">
    <div class="stat"><div class="n"><?= $stat['total'] ?></div><div class="t">资格总数</div></div>
    <div class="stat ok"><div class="n"><?= $stat['active'] ?></div><div class="t">有效</div></div>
    <div class="stat warn"><div class="n"><?= $stat['expired'] ?></div><div class="t">已过期（待续期）</div></div>
    <div class="stat bad"><div class="n"><?= $stat['revoked'] ?></div><div class="t">已停用</div></div>
</div>

<form class="toolbar" method="get" action="index.php">
    <input type="text" name="q" value="<?= e($keyword) ?>" placeholder="编号 / 姓名 / 项目" style="min-width:220px;">
    <select name="status">
        <option value="">全部状态</option>
        <option value="active"  <?= $status === 'active' ? 'selected' : '' ?>>正常（启用）</option>
        <option value="revoked" <?= $status === 'revoked' ? 'selected' : '' ?>>已停用</option>
    </select>
    <button class="btn btn-outline" type="submit">筛选</button>
    <a class="btn btn-ghost" style="color:#1d4ed8;border-color:#bfdbfe;" href="index.php">重置</a>
</form>

<div class="card">
    <div style="overflow-x:auto;">
        <table class="data">
            <thead>
            <tr>
                <th>编号</th>
                <th>姓名</th>
                <th>级别</th>
                <th>项目</th>
                <th>发证单位</th>
                <th>有效期至</th>
                <th>状态</th>
                <th style="width:200px;">操作</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" style="text-align:center;color:#64748b;padding:30px;">暂无符合条件的数据</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r):
                $s = referee_status($r); ?>
                <tr>
                    <td><code><?= e($r['referee_no']) ?></code></td>
                    <td><strong><?= e($r['name']) ?></strong></td>
                    <td><?= e($r['level']) ?></td>
                    <td><?= e($r['sport']) ?></td>
                    <td><?= e($r['issuer']) ?></td>
                    <td><?= e($r['valid_until'] ?: '长期') ?></td>
                    <td><span class="badge <?= e(status_class($s)) ?>"><?= e(status_text($s)) ?></span></td>
                    <td>
                        <a class="btn btn-outline btn-sm" href="edit.php?id=<?= (int)$r['id'] ?>">编辑</a>
                        <?php if ((int)$r['is_active'] === 1): ?>
                            <form method="post" action="toggle.php" class="inline"
                                  onsubmit="return confirm('确定停用「<?= e($r['name']) ?>（<?= e($r['referee_no']) ?>）」的裁判资格？停用后前台将显示为已停用。');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="action" value="revoke">
                                <button class="btn btn-danger btn-sm" type="submit">停用</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="toggle.php" class="inline"
                                  onsubmit="return confirm('确定重新启用该裁判资格？');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="action" value="activate">
                                <button class="btn btn-primary btn-sm" type="submit">启用</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php page_footer(); ?>
