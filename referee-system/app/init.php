<?php
/**
 * 建表 + 初始数据（幂等：重复执行不会重复插入）
 * 访问任意 API / 页面时自动执行；也可手动 php app/init.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function init_schema(): void
{
    $pdo = db();

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS admins (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at    TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
SQL);

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS referees (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    referee_code TEXT NOT NULL UNIQUE,
    name         TEXT NOT NULL,
    level        TEXT NOT NULL,
    sport        TEXT NOT NULL,
    valid_until  TEXT NOT NULL,
    issuer       TEXT NOT NULL,
    is_active    INTEGER NOT NULL DEFAULT 1,
    created_at   TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    updated_at   TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
SQL);

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS trainings (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    referee_id    INTEGER NOT NULL,
    training_date TEXT NOT NULL,
    course_name   TEXT NOT NULL,
    organizer     TEXT NOT NULL,
    result        TEXT NOT NULL DEFAULT '合格',
    created_at    TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    FOREIGN KEY (referee_id) REFERENCES referees(id) ON DELETE CASCADE
);
SQL);

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_trainings_referee ON trainings(referee_id)');

    // 初始管理员
    $cnt = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if ($cnt === 0) {
        $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
        $stmt->execute([INIT_ADMIN_USER, password_hash(INIT_ADMIN_PASS, PASSWORD_DEFAULT)]);
        echo "已创建初始管理员: " . INIT_ADMIN_USER . " / " . INIT_ADMIN_PASS . PHP_EOL;
    }

    // 示例裁判（幂等：按编号查重）
    $seedReferees = [
        ['RJ2023001', '张伟',   '国家级', '田径',   '2027-06-30', '国家体育总局田径运动管理中心', 1],
        ['RJ2024108', '李娜',   '一级',   '游泳',   date('Y-m-d', strtotime('+45 day')), '中国游泳协会', 1],
        ['RJ2022056', '王强',   '二级',   '篮球',   '2025-12-31', '北京市体育局', 1],
        ['RJ2025012', '刘洋',   '一级',   '羽毛球', '2028-03-15', '中国羽毛球协会', 1],
        ['RJ2021033', '陈静',   '三级',   '乒乓球', '2026-10-20', '上海市乒乓球协会', 0],
        ['RJ2024260', '赵磊',   '国家级', '足球',   '2029-08-31', '中国足球协会', 1],
    ];

    $check = $pdo->prepare('SELECT id FROM referees WHERE referee_code = ?');
    $insert = $pdo->prepare(
        'INSERT INTO referees (referee_code, name, level, sport, valid_until, issuer, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    $seedTrainings = [
        'RJ2023001' => ['2026-03-12', '国家级裁判年度复训班', '中国田径协会', '优秀'],
        'RJ2024108' => ['2026-05-20', '游泳裁判规则更新培训', '中国游泳协会', '合格'],
        'RJ2022056' => ['2024-11-08', '篮球二级裁判继续教育', '北京市体育局', '合格'],
        'RJ2025012' => ['2026-07-02', '羽毛球一级裁判资格培训', '中国羽毛球协会', '合格'],
        'RJ2021033' => ['2023-09-15', '乒乓球三级裁判业务培训', '上海市乒乓球协会', '合格'],
        'RJ2024260' => ['2026-08-10', '国际足联裁判体能与规则研讨', '中国足球协会', '优秀'],
    ];
    $addTraining = $pdo->prepare(
        'INSERT INTO trainings (referee_id, training_date, course_name, organizer, result)
         VALUES (?, ?, ?, ?, ?)'
    );

    foreach ($seedReferees as $r) {
        $check->execute([$r[0]]);
        if ($check->fetch()) {
            continue;
        }
        $insert->execute($r);
        $id = (int) $pdo->lastInsertId();
        if (isset($seedTrainings[$r[0]])) {
            $t = $seedTrainings[$r[0]];
            $addTraining->execute([$id, $t[0], $t[1], $t[2], $t[3]]);
        }
    }
}

// 仅在 CLI 直接运行时输出；被 include 时静默执行
$isCli = (php_sapi_name() === 'cli');
if ($isCli && realpath($argv[0] ?? '') === __FILE__) {
    init_schema();
    echo "数据库初始化完成: " . DB_PATH . PHP_EOL;
} else {
    init_schema();
}
