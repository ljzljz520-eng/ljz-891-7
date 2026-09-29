<?php
/**
 * 后台：停用 / 启用裁判资格（软停用：is_active 切换，不物理删除）
 * 权限：登录 + CSRF
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login_ajax();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_verify();

$id     = (int)($_POST['id'] ?? 0);
$action = (string)($_POST['action'] ?? '');

if (!in_array($action, ['revoke', 'activate'], true)) {
    set_flash('error', '非法操作。');
    redirect('index.php');
}

$r = find_referee_by_id($id);
if (!$r) {
    set_flash('error', '记录不存在。');
    redirect('index.php');
}

$active = $action === 'activate' ? 1 : 0;
db()->prepare('UPDATE referees SET is_active = ?, updated_at = datetime(\'now\',\'localtime\') WHERE id = ?')
    ->execute([$active, $id]);

set_flash('success', ($active ? '已启用：' : '已停用：') . $r['name'] . '（' . $r['referee_no'] . '）');
redirect('index.php');
