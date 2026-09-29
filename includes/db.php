<?php
/**
 * 数据访问层：SQLite（PDO 预处理）
 * 首次访问自动建表并写入演示数据 / 默认管理员 admin / admin123
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    init_schema($pdo);
    seed_data($pdo);

    return $pdo;
}

function init_schema(PDO $pdo): void
{
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
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    referee_no     TEXT NOT NULL UNIQUE,        -- 裁判编号
    name           TEXT NOT NULL,               -- 姓名
    level          TEXT NOT NULL,               -- 级别：国家级/一级/二级/三级
    sport          TEXT NOT NULL,               -- 项目
    issuer         TEXT NOT NULL,               -- 发证单位
    valid_from     TEXT,                        -- 发证日期
    valid_until    TEXT,                        -- 有效期截止（NULL=长期）
    is_active      INTEGER NOT NULL DEFAULT 1,  -- 1 正常 0 已停用
    remark         TEXT DEFAULT '',
    created_at     TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    updated_at     TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
SQL);

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS training_records (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    referee_id   INTEGER NOT NULL,
    train_date   TEXT NOT NULL,                  -- 培训日期
    course_name  TEXT NOT NULL,                  -- 培训课程 / 名称
    organizer    TEXT NOT NULL DEFAULT '',       -- 主办单位
    hours        REAL NOT NULL DEFAULT 0,        -- 学时
    result       TEXT NOT NULL DEFAULT '合格',   -- 考核结果
    created_at   TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    FOREIGN KEY (referee_id) REFERENCES referees(id) ON DELETE CASCADE
);
SQL);

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_training_ref ON training_records(referee_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_referee_name ON referees(name)');
}

/** 仅在空库时写入演示数据 */
function seed_data(PDO $pdo): void
{
    $cnt = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if ($cnt === 0) {
        $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)')
            ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
    }

    $cnt = (int)$pdo->query('SELECT COUNT(*) FROM referees')->fetchColumn();
    if ($cnt > 0) {
        return;
    }

    $today = date('Y-m-d');
    $seed = [
        [
            'referee_no'  => 'RF20230001',
            'name'        => '张伟',
            'level'       => '国家级',
            'sport'       => '田径',
            'issuer'      => '国家体育总局田径运动管理中心',
            'valid_from'  => '2023-03-01',
            'valid_until' => date('Y-m-d', strtotime('+400 days')),
            'is_active'   => 1,
            'remark'      => '',
            'trainings'   => [
                [date('Y-m-d', strtotime('-30 days')), '2026年田径裁判规则更新研修班', '中国田径协会', 16, '优秀'],
                [date('Y-m-d', strtotime('-200 days')), '国家级裁判员年度复训', '省体育局', 24, '合格'],
                ['2024-05-18', '反兴奋剂与赛事纪律专题培训', '国家体育总局', 8, '合格'],
            ],
        ],
        [
            'referee_no'  => 'RF20220108',
            'name'        => '李娜',
            'level'       => '一级',
            'sport'       => '篮球',
            'issuer'      => '中国篮球协会',
            'valid_from'  => '2022-06-10',
            'valid_until' => date('Y-m-d', strtotime('+90 days')),
            'is_active'   => 1,
            'remark'      => '',
            'trainings'   => [
                [date('Y-m-d', strtotime('-15 days')), '篮球新规则（2026版）解读培训', '中国篮球协会', 12, '合格'],
                ['2025-03-22', '一级裁判业务能力提升班', '省篮球运动协会', 20, '合格'],
            ],
        ],
        [
            'referee_no'  => 'RF20210456',
            'name'        => '王强',
            'level'       => '二级',
            'sport'       => '游泳',
            'issuer'      => '市体育局',
            'valid_from'  => '2021-09-01',
            'valid_until' => date('Y-m-d', strtotime('-60 days')),
            'is_active'   => 1,
            'remark'      => '证书待续期',
            'trainings'   => [
                ['2025-11-02', '游泳竞赛规则与救生安全培训', '市游泳协会', 8, '合格'],
            ],
        ],
        [
            'referee_no'  => 'RF20200777',
            'name'        => '陈静',
            'level'       => '一级',
            'sport'       => '羽毛球',
            'issuer'      => '中国羽毛球协会',
            'valid_from'  => '2020-04-15',
            'valid_until' => date('Y-m-d', strtotime('+300 days')),
            'is_active'   => 0,
            'remark'      => '违规，已停用',
            'trainings'   => [
                ['2024-08-09', '羽毛球裁判员继续教育', '中国羽毛球协会', 16, '合格'],
            ],
        ],
    ];

    $insR = $pdo->prepare(
        'INSERT INTO referees
            (referee_no, name, level, sport, issuer, valid_from, valid_until, is_active, remark)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    $insT = $pdo->prepare(
        'INSERT INTO training_records
            (referee_id, train_date, course_name, organizer, hours, result)
         VALUES (?,?,?,?,?,?)'
    );

    foreach ($seed as $r) {
        $insR->execute([
            $r['referee_no'], $r['name'], $r['level'], $r['sport'], $r['issuer'],
            $r['valid_from'], $r['valid_until'], $r['is_active'], $r['remark'],
        ]);
        $rid = (int)$pdo->lastInsertId();
        foreach ($r['trainings'] as $t) {
            $insT->execute([$rid, $t[0], $t[1], $t[2], $t[3], $t[4]]);
        }
    }
}

/* ------------------------------------------------------------------
 * 业务查询函数（全部使用预处理，防 SQL 注入）
 * ------------------------------------------------------------------ */

/** 公开查询：编号 + 姓名精确匹配；两者皆必填 */
function find_referee_public(string $refereeNo, string $name): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM referees
         WHERE referee_no = :no AND name = :name
         LIMIT 1'
    );
    $stmt->execute([':no' => $refereeNo, ':name' => $name]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function find_referee_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM referees WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** 培训记录（最近在前） */
function training_records(int $refereeId, ?int $limit = null): array
{
    $sql = 'SELECT * FROM training_records WHERE referee_id = ? ORDER BY train_date DESC, id DESC';
    if ($limit !== null) {
        $sql .= ' LIMIT ' . (int)$limit;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute([$refereeId]);
    return $stmt->fetchAll();
}

/** 后台列表，支持关键字与状态筛选 */
function list_referees(string $keyword = '', string $status = ''): array
{
    $sql  = 'SELECT * FROM referees WHERE 1=1';
    $args = [];

    if ($keyword !== '') {
        $sql .= ' AND (referee_no LIKE :kw OR name LIKE :kw OR sport LIKE :kw)';
        $args[':kw'] = '%' . $keyword . '%';
    }
    if ($status === 'active' || $status === 'revoked') {
        $sql .= ' AND is_active = ' . ($status === 'active' ? '1' : '0');
    }

    $sql .= ' ORDER BY id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function list_admins(): array
{
    return db()->query('SELECT id, username, created_at FROM admins ORDER BY id')->fetchAll();
}

function get_admin_by_username(string $username): ?array
{
    $stmt = db()->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    return $row ?: null;
}
