<?php
/**
 * 后台：保存裁判资格（新增 / 编辑）+ 培训记录（全量同步）
 * 权限：登录 + CSRF；输入全部服务端校验
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login_ajax();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_verify();

$id     = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;

if ($isEdit && !find_referee_by_id($id)) {
    set_flash('error', '记录不存在，无法保存。');
    redirect('index.php');
}

/* ---------- 1. 收集并校验基本信息 ---------- */
$refereeNo = trim((string)($_POST['referee_no'] ?? ''));
$name      = trim((string)($_POST['name'] ?? ''));
$level     = trim((string)($_POST['level'] ?? ''));
$sport     = trim((string)($_POST['sport'] ?? ''));
$issuer    = trim((string)($_POST['issuer'] ?? ''));
$validFrom = trim((string)($_POST['valid_from'] ?? ''));
$validUntil= trim((string)($_POST['valid_until'] ?? ''));
$isActive  = ((string)($_POST['is_active'] ?? '1')) === '0' ? 0 : 1;
$remark    = trim((string)($_POST['remark'] ?? ''));

$errors = [];
if ($refereeNo === '' || !preg_match('/^[A-Za-z0-9_\-]{2,40}$/', $refereeNo)) {
    $errors[] = '裁判编号需为 2-40 位字母、数字、下划线或短横线。';
}
if ($name === '' || mb_strlen($name) > 30) {
    $errors[] = '姓名必填且不超过 30 字。';
}
if (!in_array($level, referee_levels(), true)) {
    $errors[] = '裁判级别不合法。';
}
if ($sport === '' || mb_strlen($sport) > 30) {
    $errors[] = '执裁项目必填。';
}
if ($issuer === '' || mb_strlen($issuer) > 100) {
    $errors[] = '发证单位必填且不超过 100 字。';
}
$checkDate = function (string $d): bool {
    if ($d === '') return true;
    $m = [];
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d, $m)
        && (bool)date_create($d)
        && date_format(date_create($d), 'Y-m-d') === $d;
};
if (!$checkDate($validFrom))  $errors[] = '发证日期格式不正确。';
if (!$checkDate($validUntil)) $errors[] = '有效期截止格式不正确。';
if ($validFrom !== '' && $validUntil !== '' && strcmp($validUntil, $validFrom) < 0) {
    $errors[] = '有效期截止不能早于发证日期。';
}

// 编号唯一性（排除自身）
$stmt = db()->prepare('SELECT id FROM referees WHERE referee_no = ? AND id <> ? LIMIT 1');
$stmt->execute([$refereeNo, $id]);
if ($stmt->fetch()) {
    $errors[] = '裁判编号「' . $refereeNo . '」已存在，请更换。';
}

/* ---------- 2. 收集并校验培训记录（数组对齐） ---------- */
$dates   = $_POST['train_date']   ?? [];
$courses = $_POST['course_name']  ?? [];
$orgs    = $_POST['organizer']    ?? [];
$hours   = $_POST['hours']        ?? [];
$results = $_POST['result']       ?? [];
$n = max(count($dates), count($courses), count($orgs), count($hours), count($results));

$trainings = [];
for ($i = 0; $i < $n; $i++) {
    $td = trim((string)($dates[$i] ?? ''));
    $cn = trim((string)($courses[$i] ?? ''));
    $og = trim((string)($orgs[$i] ?? ''));
    $hr = trim((string)($hours[$i] ?? ''));
    $rs = trim((string)($results[$i] ?? ''));
    if ($td === '' && $cn === '' && $og === '' && $hr === '') {
        continue; // 整行空白 -> 忽略
    }
    if (!$checkDate($td)) { $errors[] = '第 ' . ($i + 1) . ' 条培训记录的日期不正确。'; }
    if ($cn === '')         { $errors[] = '第 ' . ($i + 1) . ' 条培训记录缺少课程名称。'; }
    if ($hr !== '' && (!is_numeric($hr) || (float)$hr < 0 || (float)$hr > 999)) {
        $errors[] = '第 ' . ($i + 1) . ' 条培训记录的学时需为 0-999 的数字。';
    }
    $trainings[] = [
        'train_date'  => $td,
        'course_name' => mb_substr($cn, 0, 100),
        'organizer'   => mb_substr($og, 0, 100),
        'hours'       => $hr === '' ? 0 : (float)$hr,
        'result'      => $rs === '' ? '合格' : mb_substr($rs, 0, 20),
    ];
}

if ($errors) {
    set_flash('error', implode('；', $errors));
    // 回填用户输入
    $_SESSION['old_input'] = [
        'referee' => [
            'referee_no' => $refereeNo, 'name' => $name, 'level' => $level,
            'sport' => $sport, 'issuer' => $issuer, 'valid_from' => $validFrom,
            'valid_until' => $validUntil, 'is_active' => $isActive, 'remark' => $remark,
        ],
        'training' => $trainings,
    ];
    redirect($isEdit ? 'edit.php?id=' . $id : 'edit.php');
}

/* ---------- 3. 事务写入 ---------- */
$pdo = db();
$pdo->beginTransaction();
try {
    if ($isEdit) {
        $pdo->prepare(
            'UPDATE referees SET
                referee_no=?, name=?, level=?, sport=?, issuer=?,
                valid_from=?, valid_until=?, is_active=?, remark=?,
                updated_at=datetime(\'now\',\'localtime\')
             WHERE id=?'
        )->execute([
            $refereeNo, $name, $level, $sport, $issuer,
            $validFrom ?: null, $validUntil ?: null, $isActive, $remark, $id,
        ]);
        // 培训记录全量同步（编辑场景简单可靠）
        $pdo->prepare('DELETE FROM training_records WHERE referee_id = ?')->execute([$id]);
    } else {
        $pdo->prepare(
            'INSERT INTO referees
                (referee_no, name, level, sport, issuer, valid_from, valid_until, is_active, remark)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([
            $refereeNo, $name, $level, $sport, $issuer,
            $validFrom ?: null, $validUntil ?: null, $isActive, $remark,
        ]);
        $id = (int)$pdo->lastInsertId();
    }

    $ins = $pdo->prepare(
        'INSERT INTO training_records (referee_id, train_date, course_name, organizer, hours, result)
         VALUES (?,?,?,?,?,?)'
    );
    foreach ($trainings as $t) {
        $ins->execute([$id, $t['train_date'], $t['course_name'], $t['organizer'], $t['hours'], $t['result']]);
    }

    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    set_flash('error', '保存失败：' . $ex->getMessage());
    redirect('index.php');
}

set_flash('success', ($isEdit ? '资格信息已更新。' : '新裁判资格已创建。'));
redirect('edit.php?id=' . $id);
