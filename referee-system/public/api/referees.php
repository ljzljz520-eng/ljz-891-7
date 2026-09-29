<?php
/**
 * 后台裁判资格管理接口（均需登录；写操作需 CSRF）
 *
 * GET    ?action=list&keyword=&page=&page_size=&status=
 * GET    ?action=detail&id=
 * POST   ?action=create
 * POST   ?action=update
 * POST   ?action=toggle           启用/停用
 * POST   ?action=add_training     新增培训记录
 */

require_once __DIR__ . '/../../app/bootstrap.php';

$admin  = require_admin_api();
$pdo    = db();
$action = $_GET['action'] ?? '';

/** 校验并提取资格表单 */
function validate_referee_form(array $b): array
{
    $code  = normalize_code((string) ($b['referee_code'] ?? ''));
    $name  = trim((string) ($b['name'] ?? ''));
    $level = trim((string) ($b['level'] ?? ''));
    $sport = trim((string) ($b['sport'] ?? ''));
    $until = trim((string) ($b['valid_until'] ?? ''));
    $issuer = trim((string) ($b['issuer'] ?? ''));

    if ($code === '') {
        json_fail('裁判编号不能为空');
    }
    if (!preg_match('/^[A-Za-z0-9\-]{3,30}$/', $code)) {
        json_fail('裁判编号应为 3-30 位字母、数字或连字符');
    }
    if ($name === '' || mb_strlen($name) > 30) {
        json_fail('姓名不能为空且不超过 30 个字符');
    }
    if (!in_array($level, LEVELS, true)) {
        json_fail('请选择有效的裁判级别');
    }
    if ($sport === '' || mb_strlen($sport) > 50) {
        json_fail('执裁项目不能为空且不超过 50 个字符');
    }
    if (!valid_date($until)) {
        json_fail('请选择有效的有效期日期');
    }
    if ($issuer === '' || mb_strlen($issuer) > 100) {
        json_fail('发证单位不能为空且不超过 100 个字符');
    }

    return compact('code', 'name', 'level', 'sport', 'until', 'issuer');
}

switch ($action) {

    // ---------------- 列表 ----------------
    case 'list':
        $keyword  = trim((string) ($_GET['keyword'] ?? ''));
        $status   = trim((string) ($_GET['status'] ?? ''));
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize = min(100, max(5, (int) ($_GET['page_size'] ?? 10)));
        $offset   = ($page - 1) * $pageSize;

        $where  = [];
        $params = [];
        if ($keyword !== '') {
            $where[] = '(referee_code LIKE :kw OR name LIKE :kw OR sport LIKE :kw)';
            $params[':kw'] = '%' . $keyword . '%';
        }
        $today = date('Y-m-d');
        switch ($status) {
            case 'active':
                $where[] = "is_active = 1 AND valid_until >= :today";
                break;
            case 'expiring':
                $where[] = "is_active = 1 AND valid_until >= :today
                            AND julianday(valid_until) - julianday(:today) <= :warn";
                $params[':warn'] = EXPIRY_WARN_DAYS;
                break;
            case 'expired':
                $where[] = "is_active = 1 AND valid_until < :today";
                break;
            case 'disabled':
                $where[] = 'is_active = 0';
                break;
        }
        if (in_array($status, ['active', 'expiring', 'expired', 'disabled'], true)) {
            $params[':today'] = $today;
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM referees $whereSql");
        $cntStmt->execute($params);
        $total = (int) $cntStmt->fetchColumn();

        $sql = "SELECT * FROM referees $whereSql
                ORDER BY id DESC LIMIT $pageSize OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $list = array_map('format_referee', $rows);

        // 统计概览
        $stats = [
            'total'    => (int) $pdo->query('SELECT COUNT(*) FROM referees')->fetchColumn(),
            'active'   => (int) $pdo->query("SELECT COUNT(*) FROM referees WHERE is_active=1 AND valid_until >= '$today'")->fetchColumn(),
            'expiring' => (int) $pdo->query("SELECT COUNT(*) FROM referees WHERE is_active=1 AND valid_until >= '$today' AND julianday(valid_until)-julianday('$today') <= " . EXPIRY_WARN_DAYS)->fetchColumn(),
            'expired'  => (int) $pdo->query("SELECT COUNT(*) FROM referees WHERE is_active=1 AND valid_until < '$today'")->fetchColumn(),
            'disabled' => (int) $pdo->query('SELECT COUNT(*) FROM referees WHERE is_active=0')->fetchColumn(),
        ];

        json_ok([
            'list'      => $list,
            'total'     => $total,
            'page'      => $page,
            'page_size' => $pageSize,
            'stats'     => $stats,
        ]);

    // ---------------- 详情（含全部培训记录） ----------------
    case 'detail':
        $id = (int) ($_GET['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM referees WHERE id = ?');
        $stmt->execute([$id]);
        $ref = $stmt->fetch();
        if (!$ref) {
            json_fail('资格记录不存在', 404);
        }
        $tStmt = $pdo->prepare(
            'SELECT * FROM trainings WHERE referee_id = ? ORDER BY training_date DESC, id DESC'
        );
        $tStmt->execute([$id]);

        json_ok(['referee' => format_referee($ref), 'trainings' => $tStmt->fetchAll()]);

    // ---------------- 新增 ----------------
    case 'create':
        verify_csrf();
        $b = request_body();
        $f = validate_referee_form($b);

        $dup = $pdo->prepare('SELECT id FROM referees WHERE referee_code = ?');
        $dup->execute([$f['code']]);
        if ($dup->fetch()) {
            json_fail('该裁判编号已存在，请勿重复新增');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO referees (referee_code, name, level, sport, valid_until, issuer, is_active)
             VALUES (:code, :name, :level, :sport, :until, :issuer, :active)'
        );
        $stmt->execute([
            ':code'   => $f['code'],
            ':name'   => $f['name'],
            ':level'  => $f['level'],
            ':sport'  => $f['sport'],
            ':until'  => $f['until'],
            ':issuer' => $f['issuer'],
            ':active' => isset($b['is_active']) && !$b['is_active'] ? 0 : 1,
        ]);
        $newId = (int) $pdo->lastInsertId();

        // 可随表单同时录入一条培训记录
        if (!empty($b['training_date']) && !empty($b['course_name'])) {
            $tdate = trim((string) $b['training_date']);
            $tcourse = trim((string) $b['course_name']);
            if (valid_date($tdate) && $tcourse !== '') {
                $pdo->prepare(
                    'INSERT INTO trainings (referee_id, training_date, course_name, organizer, result)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([
                    $newId, $tdate, $tcourse,
                    trim((string) ($b['organizer'] ?? '')),
                    trim((string) ($b['result'] ?? '合格')) ?: '合格',
                ]);
            }
        }

        json_ok(['message' => '新增成功', 'id' => $newId]);

    // ---------------- 编辑 ----------------
    case 'update':
        verify_csrf();
        $b = request_body();
        $id = (int) ($b['id'] ?? 0);

        $exists = $pdo->prepare('SELECT * FROM referees WHERE id = ?');
        $exists->execute([$id]);
        $ref = $exists->fetch();
        if (!$ref) {
            json_fail('资格记录不存在', 404);
        }

        $f = validate_referee_form($b);

        $dup = $pdo->prepare('SELECT id FROM referees WHERE referee_code = ? AND id <> ?');
        $dup->execute([$f['code'], $id]);
        if ($dup->fetch()) {
            json_fail('该裁判编号已被其他记录占用');
        }

        $stmt = $pdo->prepare(
            "UPDATE referees
             SET referee_code = :code, name = :name, level = :level, sport = :sport,
                 valid_until = :until, issuer = :issuer, is_active = :active,
                 updated_at = datetime('now','localtime')
             WHERE id = :id"
        );
        $stmt->execute([
            ':code'   => $f['code'],
            ':name'   => $f['name'],
            ':level'  => $f['level'],
            ':sport'  => $f['sport'],
            ':until'  => $f['until'],
            ':issuer' => $f['issuer'],
            ':active' => (int) !empty($b['is_active']),
            ':id'     => $id,
        ]);

        json_ok(['message' => '保存成功']);

    // ---------------- 启用 / 停用 ----------------
    case 'toggle':
        verify_csrf();
        $b  = request_body();
        $id = (int) ($b['id'] ?? 0);

        $stmt = $pdo->prepare('SELECT is_active FROM referees WHERE id = ?');
        $stmt->execute([$id]);
        $ref = $stmt->fetch();
        if (!$ref) {
            json_fail('资格记录不存在', 404);
        }

        // 显式传入 is_active 则以传入为准，否则切换
        $newActive = array_key_exists('is_active', $b)
            ? ((int) !empty($b['is_active']))
            : (1 - (int) $ref['is_active']);

        $pdo->prepare(
            "UPDATE referees SET is_active = ?, updated_at = datetime('now','localtime') WHERE id = ?"
        )->execute([$newActive, $id]);

        json_ok(['message' => $newActive ? '已启用' : '已停用', 'is_active' => $newActive]);

    // ---------------- 新增培训记录 ----------------
    case 'add_training':
        verify_csrf();
        $b      = request_body();
        $id     = (int) ($b['referee_id'] ?? 0);
        $date   = trim((string) ($b['training_date'] ?? ''));
        $course = trim((string) ($b['course_name'] ?? ''));
        $org    = trim((string) ($b['organizer'] ?? ''));
        $result = trim((string) ($b['result'] ?? '合格')) ?: '合格';

        $check = $pdo->prepare('SELECT id FROM referees WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) {
            json_fail('资格记录不存在', 404);
        }
        if (!valid_date($date)) {
            json_fail('请选择有效的培训日期');
        }
        if ($course === '' || mb_strlen($course) > 100) {
            json_fail('培训名称不能为空且不超过 100 个字符');
        }
        if (mb_strlen($org) > 100 || mb_strlen($result) > 30) {
            json_fail('主办单位或考核结果字段过长');
        }

        $pdo->prepare(
            'INSERT INTO trainings (referee_id, training_date, course_name, organizer, result)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$id, $date, $course, $org, $result]);

        json_ok(['message' => '培训记录已添加', 'id' => (int) $pdo->lastInsertId()]);

    default:
        json_fail('未知操作', 404);
}
